<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Observer;

use Klevu\Indexing\Model\Update\Attribute as AttributeUpdate;
use Klevu\IndexingApi\Service\AttributeUpdateResponderServiceInterface;
use Magento\Catalog\Api\Data\CategoryAttributeInterface;
use Magento\Catalog\Model\Entity\Attribute;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\ConfigFactory as EavConfigFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class CategoryAttributeObserver implements ObserverInterface
{
    /**
     * @var AttributeUpdateResponderServiceInterface
     */
    private readonly AttributeUpdateResponderServiceInterface $responderService;
    /**
     * @var EavConfigFactory
     */
    private readonly EavConfigFactory $eavConfigFactory;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param AttributeUpdateResponderServiceInterface $responderService
     * @param EavConfigFactory $eavConfigFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        AttributeUpdateResponderServiceInterface $responderService,
        EavConfigFactory $eavConfigFactory,
        LoggerInterface $logger,
    ) {
        $this->responderService = $responderService;
        $this->eavConfigFactory = $eavConfigFactory;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        /** @var Attribute $attribute */
        $attribute = $event->getData(key: 'attribute');
        if (!($attribute instanceof CategoryAttributeInterface)) {
            return;
        }
        if ((int)$attribute->getData('entity_type_id') !== $this->getEntityTypeId()) {
            return;
        }
        $this->responderService->execute([
            AttributeUpdate::ATTRIBUTE_IDS => [(int)$attribute->getId()],
            AttributeUpdate::STORE_IDS => [],
        ]);
    }

    /**
     * @return int|null
     */
    private function getEntityTypeId(): ?int
    {
        /** @var EavConfig $eavConfig */
        $eavConfig = $this->eavConfigFactory->create();
        try {
            $entityType = $eavConfig->getEntityType(CategoryAttributeInterface::ENTITY_TYPE_CODE);
        } catch (LocalizedException $exception) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );

            return null;
        }

        return $entityType->getEntityTypeId()
            ? (int)$entityType->getEntityTypeId()
            : null;
    }
}
