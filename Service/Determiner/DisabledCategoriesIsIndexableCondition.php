<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Service\Determiner;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\IndexingApi\Service\Determiner\IsIndexableConditionInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

class DisabledCategoriesIsIndexableCondition implements IsIndexableConditionInterface
{
    public const XML_PATH_EXCLUDE_DISABLED_CATEGORIES = 'klevu/indexing/exclude_disabled_categories';

    /**
     * @var ScopeConfigInterface
     */
    private readonly ScopeConfigInterface $scopeConfig;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var ScopeProviderInterface
     */
    private readonly ScopeProviderInterface $scopeProvider;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param ScopeProviderInterface $scopeProvider
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        ScopeProviderInterface $scopeProvider,
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->scopeProvider = $scopeProvider;
    }

    /**
     * @param ExtensibleDataInterface|PageInterface $entity
     * @param StoreInterface $store
     * @param string $entitySubtype
     *
     * @return bool
     */
    public function execute(
        ExtensibleDataInterface|PageInterface $entity,
        StoreInterface $store,
        string $entitySubtype = '', // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
    ): bool {
        if (!($entity instanceof CategoryInterface)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid argument provided for "$entity". Expected %s, received %s.',
                    CategoryInterface::class,
                    get_debug_type($entity),
                ),
            );
        }
        return !$this->isCheckEnabled() || $this->isIndexable(category: $entity, store: $store);
    }

    /**
     * @return bool
     */
    private function isCheckEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_EXCLUDE_DISABLED_CATEGORIES,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            null,
        );
    }

    /**
     * @param CategoryInterface $category
     * @param StoreInterface $store
     *
     * @return bool
     */
    private function isIndexable(CategoryInterface $category, StoreInterface $store): bool
    {
        $isCategoryEnabled = $this->isCategoryEnabled(entity: $category);
        if (!$isCategoryEnabled) {
            $currentScope = $this->scopeProvider->getCurrentScope();
            $this->scopeProvider->setCurrentScope(scope: $store);
            $this->logger->debug(
                // phpcs:ignore Generic.Files.LineLength.TooLong
                message: 'Store ID: {storeId} Category ID: {categoryId} not indexable due to Is Active: {isActive} in {method}',
                context: [
                    'storeId' => $store->getId(),
                    'categoryId' => $category->getId(),
                    'isActive' => $category->getIsActive(),
                    'method' => __METHOD__,
                ],
            );
            if ($currentScope->getScopeObject()) {
                $this->scopeProvider->setCurrentScope(scope: $currentScope->getScopeObject());
            } else {
                $this->scopeProvider->unsetCurrentScope();
            }
        }

        return $isCategoryEnabled;
    }

    /**
     * @param CategoryInterface $entity
     *
     * @return bool
     */
    private function isCategoryEnabled(CategoryInterface $entity): bool
    {
        return (bool)$entity->getIsActive();
    }
}
