<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Observer;

use Klevu\Indexing\Model\Update\Entity;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CategoryDeleteObserver implements ObserverInterface
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
        /** @var Category $category */
        $category = $event->getData('entity');
        if (!($category instanceof CategoryInterface)) {
            return;
        }

        $this->responderService->execute([
            Entity::ENTITY_IDS => [(int)$category->getId()],
        ]);
    }
}
