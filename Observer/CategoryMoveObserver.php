<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Observer;

use Klevu\Indexing\Model\Update\Entity;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CategoryMoveObserver implements ObserverInterface
{
    /**
     * @var EntityUpdateResponderServiceInterface
     */
    private readonly EntityUpdateResponderServiceInterface $responderService;

    /**
     * @param EntityUpdateResponderServiceInterface $responderService
     */
    public function __construct(EntityUpdateResponderServiceInterface $responderService)
    {
        $this->responderService = $responderService;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $data = $this->getDataFromEvent($event);
        if (!$data) {
            return;
        }
        $this->responderService->execute($data);
    }

    /**
     * @param Event $event
     *
     * @return int[][]|null
     */
    private function getDataFromEvent(Event $event): ?array
    {
        $categoryMove = $event->getData();
        if (!($categoryMove['category_id'] ?? null)) {
            return null;
        }
        if (!($categoryMove['parent_id'] ?? null)) {
            return null;
        }
        if (!($categoryMove['prev_parent_id'] ?? null)) {
            return null;
        }

        return [
            Entity::ENTITY_IDS => [
                (int)$categoryMove['category_id'],
                (int)$categoryMove['parent_id'],
                (int)$categoryMove['prev_parent_id'],
            ],
        ];
    }
}
