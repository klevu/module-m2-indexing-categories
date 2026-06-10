<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Plugin\Console\Command\EntitySyncInformationCommand;

use Klevu\Indexing\Console\Command\EntitySyncInformationCommand;
use Klevu\IndexingApi\Api\Data\EntitySyncConditionsValuesInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Store\Api\GroupRepositoryInterface as StoreGroupRepositoryInterface;

class AddCategorySyncInformationPlugin
{
    /**
     * @var StoreGroupRepositoryInterface
     */
    private readonly StoreGroupRepositoryInterface $storeGroupRepository;

    /**
     * @param StoreGroupRepositoryInterface $storeGroupRepository
     */
    public function __construct(
        StoreGroupRepositoryInterface $storeGroupRepository,
    ) {
        $this->storeGroupRepository = $storeGroupRepository;
    }

    /**
     * @param EntitySyncInformationCommand $subject
     * @param array<array<string, mixed>> $result
     * @param string $targetEntityType
     * @param EntitySyncConditionsValuesInterface $conditionsValuesData
     *
     * @return array<array<string, mixed>>
     */
    public function afterGetRealTimeSyncInformationData(
        EntitySyncInformationCommand $subject, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter, Generic.Files.LineLength.TooLong
        array $result,
        string $targetEntityType,
        EntitySyncConditionsValuesInterface $conditionsValuesData,
    ): array {
        if ('KLEVU_CATEGORY' !== $targetEntityType) {
            return $result;
        }

        $category = $conditionsValuesData->getTargetEntity();
        if (!($category instanceof CategoryInterface)) {
            return $result;
        }

        $store = $conditionsValuesData->getStore();
        $storeGroup = $this->storeGroupRepository->get(
            id: (int)$store->getStoreGroupId(),
        );

        $categoryPathIds = $category->getPathIds();
        array_pop($categoryPathIds);
        $categoryRootPathId = array_pop($categoryPathIds);

        $categoryBelongsToStore = ((int)$storeGroup->getRootCategoryId() === (int)$categoryRootPathId);

        $result[] = [
            'Category belongs to store hierarchy' => __($categoryBelongsToStore ? 'Yes' : 'No')->render(),
            'Is Active' => __($category->getIsActive() ? 'Yes' : 'No')->render(),
        ];

        return $result;
    }
}
