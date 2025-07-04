<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Service\Provider;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Filter\FilterManager;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Psr\Log\LoggerInterface;

class CategoryUrlProvider implements CategoryUrlProviderInterface
{
    /**
     * @var LoggerInterface 
     */
    private readonly LoggerInterface $logger;
    /**
     * @var UrlInterface 
     */
    private readonly UrlInterface $urlBuilder;
    /**
     * @var UrlFinderInterface 
     */
    private readonly UrlFinderInterface $urlFinder;
    /**
     * @var CategoryRepositoryInterface
     */
    private readonly CategoryRepositoryInterface $categoryRepository;
    /**
     * @var ScopeProviderInterface
     */
    private readonly ScopeProviderInterface $scopeProvider;
    /**
     * @var FilterManager 
     */
    private readonly FilterManager $filter;
    /**
     * @var array<string, string>
     */
    private array $cachedUrls = [];

    /**
     * @param LoggerInterface $logger
     * @param UrlInterface $urlBuilder
     * @param UrlFinderInterface $urlFinder
     * @param CategoryRepositoryInterface $categoryRepository
     * @param ScopeProviderInterface $scopeProvider
     * @param FilterManager $filter
     */
    public function __construct(
        LoggerInterface $logger,
        UrlInterface $urlBuilder,
        UrlFinderInterface $urlFinder,
        CategoryRepositoryInterface $categoryRepository,
        ScopeProviderInterface $scopeProvider,
        FilterManager $filter,
    ) {
        $this->logger = $logger;
        $this->urlBuilder = $urlBuilder;
        $this->urlFinder = $urlFinder;
        $this->categoryRepository = $categoryRepository;
        $this->scopeProvider = $scopeProvider;
        $this->filter = $filter;
    }
    
    /**
     * @param CategoryInterface $category
     * @param int|null $storeId
     *
     * @return string
     * 
     * @see \Magento\Catalog\Model\Category::getUrl But we don't want caching as we may be switching stores
     */
    public function getForCategory(
        CategoryInterface $category,
        ?int $storeId,
    ): string {
        $storeId ??= $this->getStoreId();
        $cacheKey = $category->getId() . '-' . $storeId;
        
        if (isset($this->cachedUrls[$cacheKey])) {
            return $this->cachedUrls[$cacheKey];
        }

        if (!($category instanceof DataObject)) {
            $this->logger->error(
                message: 'Category URL generation failed because the category is not a DataObject',
                context: [
                    'method' => __METHOD__,
                    'line' => __LINE__,
                    'category' => $category,
                ],
            );
            
            return '';
        }
        
        // Intentionally not getDataUsingMethod in case any implementation messes with autogeneration
        $requestPath = $category->getData('request_path');
        if ($requestPath) {
            $this->cachedUrls[$cacheKey] = $this->urlBuilder->getDirectUrl(
                url: $requestPath,
                params: [
                    '_scope' => $storeId,
                    '_secure' => 1,
                ],
            );

            return $this->cachedUrls[$cacheKey];
        }

        // Previous early return avoids unecessary rewrite table lookup
        $urlRewrite = $this->urlFinder->findOneByData(
            data: [
                UrlRewrite::ENTITY_ID => $category->getId(),
                UrlRewrite::ENTITY_TYPE => CategoryUrlRewriteGenerator::ENTITY_TYPE,
                UrlRewrite::STORE_ID => $storeId,
                UrlRewrite::REDIRECT_TYPE => 0,
            ],
        );
        if ($urlRewrite) {
            $this->cachedUrls[$cacheKey] = $this->urlBuilder->getDirectUrl(
                url: $urlRewrite->getRequestPath(),
                params: [
                    '_scope' => $storeId,
                    '_secure' => 1,
                ],
            );

            return $this->cachedUrls[$cacheKey];
        }

        // Again, we avoid core method getCategoryIdUrl(), not only because it doesn't appear in the interface,
        //  but because the category data may be cached
        $urlKey = $category->getUrlKey();
        if (!$urlKey) {
            $categoryName = $category->getDataUsingMethod('name');
            $urlKey = method_exists($category, 'formatUrlKey')
                ? $category->formatUrlKey($categoryName)
                : $this->filter->translitUrl($categoryName);
        }

        $this->cachedUrls[$cacheKey] = $this->urlBuilder->getUrl(
            routePath: 'catalog/category/view',
            routeParams: [
                's' => $urlKey,
                'id' => $category->getId(),
                '_scope' => $storeId,
                '_secure' => 1,
            ],
        );
        
        return $this->cachedUrls[$cacheKey];
    }

    /**
     * @param int $categoryId
     * @param int|null $storeId
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getForCategoryId(
        int $categoryId,
        ?int $storeId,
    ): string {
        $category = $this->categoryRepository->get(
            categoryId: $categoryId,
            storeId: $storeId ?? $this->getStoreId(),
        );
        
        return $this->getForCategory(
            category: $category,
            storeId: $storeId,
        );
    }

    /**
     * @return void
     */
    public function clearCache(): void
    {
        $this->cachedUrls = [];
        $this->urlBuilder->_resetState();
    }

    /**
     * @return int
     */
    private function getStoreId(): int
    {
        $scope = $this->scopeProvider->getCurrentScope();

        $return = 0;
        switch ($scope->getScopeType()) {
            case ScopeConfigInterface::SCOPE_TYPE_DEFAULT:
                break;

            case ScopeInterface::SCOPE_WEBSITES:
                $website = $scope->getScopeObject();
                if (
                    $website instanceof WebsiteInterface
                    && method_exists($website, 'getDefaultStore')
                ) {
                    $defaultStore = $website->getDefaultStore();
                    if (method_exists($defaultStore, 'getId')) {
                        $return = (int)$defaultStore->getId();
                    } else {
                        $this->logger->warning(
                            message: 'Cannot determine ID of default store from website scope object',
                            context: [
                                'method' => __METHOD__,
                                'line' => __LINE__,
                                'scopeObject' => $website,
                                'defaultStore' => $defaultStore,
                            ],
                        );
                    }
                } else {
                    $this->logger->warning(
                        message: 'Cannot determine default store from website scope object',
                        context: [
                            'method' => __METHOD__,
                            'line' => __LINE__,
                            'scopeObject' => $website,
                        ],
                    );
                }
                break;

            case ScopeInterface::SCOPE_STORES:
                /** @var StoreInterface $store */
                $store = $scope->getScopeObject();
                $return = (int)$store->getId();
                break;
        }

        return $return;
    }
}
