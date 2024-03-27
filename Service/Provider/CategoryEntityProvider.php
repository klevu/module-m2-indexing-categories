<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Service\Provider;

use Klevu\Configuration\Service\Provider\ScopeConfigProviderInterface;
use Klevu\IndexingApi\Service\Provider\EntityProviderInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Eav\Model\Entity;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\GroupRepositoryInterface;
use Psr\Log\LoggerInterface;

class CategoryEntityProvider implements EntityProviderInterface
{
    /**
     * @var CategoryCollectionFactory
     */
    private readonly CategoryCollectionFactory $categoryCollectionFactory;
    /**
     * @var ScopeConfigProviderInterface
     */
    private readonly ScopeConfigProviderInterface $syncEnabledProvider;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var GroupRepositoryInterface
     */
    private readonly GroupRepositoryInterface $groupRepository;

    /**
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param ScopeConfigProviderInterface $syncEnabledProvider
     * @param LoggerInterface $logger
     * @param GroupRepositoryInterface $groupRepository
     */
    public function __construct(
        CategoryCollectionFactory $categoryCollectionFactory,
        ScopeConfigProviderInterface $syncEnabledProvider,
        LoggerInterface $logger,
        GroupRepositoryInterface $groupRepository,
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->syncEnabledProvider = $syncEnabledProvider;
        $this->logger = $logger;
        $this->groupRepository = $groupRepository;
    }

    /**
     * @param StoreInterface|null $store
     * @param int[]|null $entityIds
     *
     * @return \Generator|null
     * @throws LocalizedException
     */
    public function get(?StoreInterface $store = null, ?array $entityIds = []): ?\Generator
    {
        if (!$this->syncEnabledProvider->get()) {
            return null;
        }
        $categoryCollection = $this->getCollection(store: $store, entityIds: $entityIds);
        foreach ($categoryCollection as $category) {
            yield $category;
        }
    }

    /**
     * @param StoreInterface|null $store
     * @param int[]|null $entityIds
     *
     * @return CategoryCollection
     * @throws LocalizedException
     */
    private function getCollection(?StoreInterface $store, ?array $entityIds): CategoryCollection
    {
        /** @var CategoryCollection $categoryCollection */
        $categoryCollection = $this->categoryCollectionFactory->create();
        $categoryCollection->addAttributeToSelect(attribute: '*');
        $categoryCollection->addFieldToFilter('path', ['neq' => '1']);
        if ($store) {
            $categoryCollection->setStore(store: (int)$store->getId());
            $group = $this->groupRepository->get(id: $store->getStoreGroupId());
            $categoryCollection->addPathsFilter(
                paths: [Category::TREE_ROOT_ID . '/' . $group->getRootCategoryId() . '/'],
            );
        }
        if ($entityIds) {
            $categoryCollection->addFieldToFilter(
                Entity::DEFAULT_ENTITY_ID_FIELD,
                ['in' => implode(',', $entityIds)],
            );
        }
        $this->logQuery(collection: $categoryCollection);

        return $categoryCollection;
    }

    /**
     * @param CategoryCollection $collection
     *
     * @return void
     */
    private function logQuery(CategoryCollection $collection): void
    {
        $this->logger->debug(
            message: 'Method: {method}, Debug: {message}',
            context: [
                'method' => __METHOD__,
                'message' =>
                    sprintf(
                        'Category Entity Provider Query: %s',
                        $collection->getSelect()->__toString(),
                    ),
            ],
        );
    }
}
