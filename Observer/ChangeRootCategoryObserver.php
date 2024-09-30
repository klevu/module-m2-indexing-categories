<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Observer;

use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Api\Data\GroupInterface;
use Magento\Store\Model\Group;

class ChangeRootCategoryObserver implements ObserverInterface
{
    /**
     * @var EntityDiscoveryOrchestratorServiceInterface
     */
    private readonly EntityDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService;

    /**
     * @param EntityDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService
     */
    public function __construct(EntityDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService)
    {
        $this->discoveryOrchestratorService = $discoveryOrchestratorService;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $group = $event->getData('store_group');
        if (!($group instanceof GroupInterface)) {
            return;
        }
        if (!$this->hasRootCategoryChanged($group)) {
            return;
        }
        $this->discoveryOrchestratorService->execute(entityTypes: ['KLEVU_CATEGORY']);
    }

    /**
     * @param GroupInterface $group
     *
     * @return bool
     */
    private function hasRootCategoryChanged(GroupInterface $group): bool
    {
        /** @var Group $group */
        return (int)$group->getOrigData('root_category_id') !== (int)$group->getData('root_category_id');
    }
}
