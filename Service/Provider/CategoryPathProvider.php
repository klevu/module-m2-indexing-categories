<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Service\Provider;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class CategoryPathProvider implements CategoryPathProviderInterface
{
    public const CATEGORY_PATH_SEPARATOR_KLEVU = ';';
    public const CATEGORY_PATH_SEPARATOR_MAGENTO = '/';

    /**
     * @var CategoryRepositoryInterface
     */
    private readonly CategoryRepositoryInterface $categoryRepository;
    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;
    /**
     * @var ScopeProviderInterface
     */
    private readonly ScopeProviderInterface $scopeProvider;
    /**
     * @var int|null
     */
    private ?int $storeRootCategory = null;
    /**
     * @var int|null
     */
    private ?int $storeId = null;

    /**
     * @param CategoryRepositoryInterface $categoryRepository
     * @param StoreManagerInterface $storeManager
     * @param ScopeProviderInterface $scopeProvider
     */
    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        StoreManagerInterface $storeManager,
        ScopeProviderInterface $scopeProvider,
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->storeManager = $storeManager;
        $this->scopeProvider = $scopeProvider;
    }

    /**
     * @param CategoryInterface $category
     * @param string $curPath
     * @param int[]|null $excludeCategoryIds
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getForCategory(
        CategoryInterface $category,
        string $curPath = '',
        ?array $excludeCategoryIds = null,
    ): string {
        if (null === $excludeCategoryIds) {
            $categoryPath = $category->getPath();
            $categoryPathArray = explode(separator: static::CATEGORY_PATH_SEPARATOR_MAGENTO, string: $categoryPath);
            $excludeCategoryIds = array_map(
                callback: 'intval',
                array: array_slice(array: $categoryPathArray, offset: 0, length: 2),
            );
        }
        if (in_array((int)$category->getId(), $excludeCategoryIds, true)) {
            return $curPath;
        }
        if ($curPath) {
            $curPath = static::CATEGORY_PATH_SEPARATOR_KLEVU . $curPath;
        }
        $curPath = $category->getName() . $curPath;
        $parentCategory = $this->getParentCategory($category);

        return $parentCategory
            ? $this->getForCategory(
                category: $parentCategory,
                curPath: $curPath,
                excludeCategoryIds: $excludeCategoryIds,
            )
            : $curPath;
    }

    /**
     * @param int $categoryId
     * @param int|null $storeId
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getForCategoryId(int $categoryId, ?int $storeId = null): string
    {
        if (null === $storeId) {
            $storeId = $this->getStoreId();
        }
        $category = $this->categoryRepository->get(
            categoryId: $categoryId,
            storeId: $storeId,
        );

        return $this->getForCategory(
            category: $category,
        );
    }

    /**
     * @param CategoryInterface $category
     *
     * @return CategoryInterface|null
     * @throws NoSuchEntityException
     */
    private function getParentCategory(
        CategoryInterface $category,
    ): ?CategoryInterface {
        if (!$category->getParentId()) {
            return null;
        }
        $storeId = $this->getStoreId();
        $return = $this->categoryRepository->get(
            categoryId: (int)$category->getParentId(),
            storeId: $storeId,
        );

        return ((int)$return->getId() !== $this->getRootCategory($storeId))
            ? $return
            : null;
    }

    /**
     * @param int|null $storeId
     *
     * @return int|null
     * @throws NoSuchEntityException
     */
    private function getRootCategory(?int $storeId = null): ?int
    {
        if (null === $this->storeRootCategory) {
            $store = $this->storeManager->getStore($storeId);
            $rootCategoryId = (method_exists($store, 'getRootCategoryId'))
                ? (int)$store->getRootCategoryId()
                : null;
            $this->storeRootCategory = $rootCategoryId
                ? (int)$rootCategoryId
                : null;
        }

        return $this->storeRootCategory;
    }

    /**
     * @return int|null
     */
    private function getStoreId(): ?int
    {
        if (!$this->storeId) {
            $scope = $this->scopeProvider->getCurrentScope();
            if ($scope->getScopeType() === ScopeInterface::SCOPE_STORES) {
                $this->storeId = (int)$scope->getScopeId();
            }
        }

        return $this->storeId;
    }
}
