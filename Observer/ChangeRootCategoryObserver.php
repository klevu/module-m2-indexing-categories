<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Observer;

use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Api\Data\GroupInterface;
use Magento\Store\Model\Group;
use Psr\Log\LoggerInterface;

class ChangeRootCategoryObserver implements ObserverInterface
{
    /**
     * @var EntityDiscoveryOrchestratorServiceInterface
     */
    private readonly EntityDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService;
    /**
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $logger;

    /**
     * @param EntityDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        EntityDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService,
        ?LoggerInterface $logger = null,
    ) {
        $this->discoveryOrchestratorService = $discoveryOrchestratorService;
        $this->logger = $logger ?: ObjectManager::getInstance()->get(LoggerInterface::class);
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
        $responsesGenerator = $this->discoveryOrchestratorService->execute(entityTypes: ['KLEVU_CATEGORY']);
        foreach ($responsesGenerator as $responses) {
            $count = 1;
            foreach ($responses as $response) {
                $this->logger->debug(
                    message: sprintf(
                        'Discover %s to %s Batch %s %s.',
                        $response->getEntityType(),
                        $response->getAction(),
                        $count,
                        $response->isSuccess() ? 'Completed Successfully' : 'Failed',
                    ),
                    context: [
                        'method' => __METHOD__,
                        'line' => __LINE__,
                    ],
                );
                $count++;
            }
        }
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
