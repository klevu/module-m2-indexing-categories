<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Service;

use Klevu\Indexing\Model\Update\Attribute;
use Klevu\IndexingApi\Model\Update\AttributeInterface;
use Klevu\IndexingApi\Model\Update\AttributeInterfaceFactory as AttributeUpdateInterfaceFactory;
use Klevu\IndexingApi\Service\AttributeUpdateResponderServiceInterface;
use Magento\Framework\Event\ManagerInterface;
use Psr\Log\LoggerInterface;

class AttributeUpdateResponderService implements AttributeUpdateResponderServiceInterface
{
    /**
     * @var ManagerInterface
     */
    private readonly ManagerInterface $eventManager;
    /**
     * @var AttributeUpdateInterfaceFactory
     */
    private readonly AttributeUpdateInterfaceFactory $attributeUpdateFactory;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param ManagerInterface $eventManager
     * @param AttributeUpdateInterfaceFactory $attributeUpdateFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        ManagerInterface $eventManager,
        AttributeUpdateInterfaceFactory $attributeUpdateFactory,
        LoggerInterface $logger,
    ) {
        $this->eventManager = $eventManager;
        $this->attributeUpdateFactory = $attributeUpdateFactory;
        $this->logger = $logger;
    }

    /**
     * @param mixed[] $data
     *
     * @return void
     */
    public function execute(array $data): void
    {
        if (empty($data[Attribute::ATTRIBUTE_IDS])) {
            $this->logger->debug(
                message: 'Method: {method}, Debug: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => 'No attribute Ids provided for KLEVU_CATEGORY attribute update.',
                ]);

            return;
        }
        try {
            $attributeUpdate = $this->createAttributeUpdate($data);
        } catch (\InvalidArgumentException $exception) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );

            return;
        }

        $this->eventManager->dispatch(
            'klevu_indexing_attribute_update',
            [
                'attributeUpdate' => $attributeUpdate,
            ],
        );
    }

    /**
     * @param mixed[] $data
     *
     * @return AttributeInterface
     * @throws \InvalidArgumentException
     */
    private function createAttributeUpdate(array $data): AttributeInterface
    {
        return $this->attributeUpdateFactory->create([
            'data' => [
                Attribute::ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
                Attribute::ATTRIBUTE_IDS => $data[Attribute::ATTRIBUTE_IDS],
                Attribute::STORE_IDS => $data[Attribute::STORE_IDS] ?? [],
            ],
        ]);
    }
}
