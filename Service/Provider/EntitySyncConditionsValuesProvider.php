<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Service\Provider;

use Klevu\Configuration\Service\Provider\ApiKeysProviderInterface;
use Klevu\Configuration\Service\Provider\StoresProviderInterface;
use Klevu\IndexingApi\Api\Data\EntitySyncConditionsValuesInterface;
use Klevu\IndexingApi\Api\Data\EntitySyncConditionsValuesInterfaceFactory;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Service\Determiner\IsIndexableDeterminerInterface;
use Klevu\IndexingApi\Service\Provider\EntitySyncConditionsValuesProviderInterface;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;

class EntitySyncConditionsValuesProvider implements EntitySyncConditionsValuesProviderInterface
{
    /**
     * @var EntitySyncConditionsValuesInterfaceFactory
     */
    private readonly EntitySyncConditionsValuesInterfaceFactory $entitySyncConditionsValuesFactory;
    /**
     * @var IndexingEntityProviderInterface
     */
    private readonly IndexingEntityProviderInterface $indexingEntityProvider;
    /**
     * @var ApiKeysProviderInterface
     */
    private readonly ApiKeysProviderInterface $apiKeysProvider;
    /**
     * @var StoresProviderInterface
     */
    private readonly StoresProviderInterface $storesProvider;
    /**
     * @var IsIndexableDeterminerInterface
     */
    private readonly IsIndexableDeterminerInterface $isIndexableDeterminer;
    /**
     * @var CategoryRepositoryInterface
     */
    private readonly CategoryRepositoryInterface $categoryRepository;

    /**
     * @param EntitySyncConditionsValuesInterfaceFactory $entitySyncConditionsValuesFactory
     * @param IndexingEntityProviderInterface $indexingEntityProvider
     * @param ApiKeysProviderInterface $apiKeysProvider
     * @param StoresProviderInterface $storesProvider
     * @param IsIndexableDeterminerInterface $isIndexableDeterminer
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(
        EntitySyncConditionsValuesInterfaceFactory $entitySyncConditionsValuesFactory,
        IndexingEntityProviderInterface $indexingEntityProvider,
        ApiKeysProviderInterface $apiKeysProvider,
        StoresProviderInterface $storesProvider,
        IsIndexableDeterminerInterface $isIndexableDeterminer,
        CategoryRepositoryInterface $categoryRepository,
    ) {
        $this->entitySyncConditionsValuesFactory = $entitySyncConditionsValuesFactory;
        $this->indexingEntityProvider = $indexingEntityProvider;
        $this->apiKeysProvider = $apiKeysProvider;
        $this->storesProvider = $storesProvider;
        $this->isIndexableDeterminer = $isIndexableDeterminer;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * @param string $targetEntityType
     * @param int $targetEntityId
     *
     * @return EntitySyncConditionsValuesInterface[]
     */
    public function get(
        string $targetEntityType,
        int $targetEntityId,
    ): array {
        if ('KLEVU_CATEGORY' !== $targetEntityType) {
            throw new \InvalidArgumentException(
                message: sprintf(
                    'Provider of type %s cannot be used to retrieve information about entities of type %s',
                    self::class,
                    $targetEntityType,
                ),
            );
        }

        $return = [];

        $apiKeys = $this->apiKeysProvider->get(storeIds: []);
        $indexingEntities = $this->indexingEntityProvider->get(
            entityType: 'KLEVU_CATEGORY',
            apiKeys: $apiKeys,
            entityIds: [$targetEntityId],
        );

        foreach ($apiKeys as $apiKey) {
            $storesForApiKey = $this->storesProvider->get(
                apiKey: $apiKey,
            );

            foreach ($storesForApiKey as $store) {
                try {
                    $categoryInStore = $this->categoryRepository->get(
                        categoryId: (int)$targetEntityId,
                        storeId: (int)$store->getId(),
                    );
                } catch (NoSuchEntityException) {
                    $categoryInStore = null;
                }

                $discoveredIndexingEntities = array_filter(
                    array: $indexingEntities,
                    callback: static fn (IndexingEntityInterface $indexingEntity) => (
                        $indexingEntity->getApiKey() === $apiKey
                        && $indexingEntity->getTargetId() === (int)$targetEntityId
                        && !$indexingEntity->getTargetParentId()
                    ),
                );

                $return[] = $this->createItem(
                    apiKey: $apiKey,
                    store: $store,
                    categoryInStore: $categoryInStore,
                    indexingEntity: $discoveredIndexingEntities
                        ? current($discoveredIndexingEntities)
                        : null,
                );
            }
        }

        return $return;
    }

    /**
     * @param string $apiKey
     * @param StoreInterface $store
     * @param CategoryInterface|null $categoryInStore
     * @param IndexingEntityInterface|null $indexingEntity
     *
     * @return EntitySyncConditionsValuesInterface
     */
    private function createItem(
        string $apiKey,
        StoreInterface $store,
        ?CategoryInterface $categoryInStore,
        ?IndexingEntityInterface $indexingEntity,
    ): EntitySyncConditionsValuesInterface {
        $item = $this->entitySyncConditionsValuesFactory->create();

        $item->setApiKey($apiKey);
        $item->setStore($store);
        $item->setTargetEntityType('KLEVU_CATEGORY');
        if ($categoryInStore) {
            $item->setTargetEntity($categoryInStore);
        }
        if ($indexingEntity) {
            $item->setIndexingEntity($indexingEntity);
        }

        $item->setIsIndexable(
            isIndexable: $categoryInStore && $this->isIndexableDeterminer->execute(
                entity: $categoryInStore,
                store: $store,
                entitySubtype: $indexingEntity?->getTargetEntitySubtype() ?? '',
            ),
        );
        $item->addSyncConditionsValue(
            key: 'is_active',
            value: $categoryInStore && $categoryInStore->getIsActive(),
        );

        return $item;
    }
}