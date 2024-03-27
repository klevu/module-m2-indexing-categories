<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Service;

use Klevu\Indexing\Model\Update\Attribute as AttributeUpdate;
use Klevu\IndexingApi\Model\Update\AttributeInterfaceFactory as AttributeUpdateInterfaceFactory;
use Klevu\IndexingApi\Service\AttributeUpdateResponderServiceInterface;
use Klevu\IndexingCategories\Service\AttributeUpdateResponderService;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AttributeUpdateResponderServiceTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = AttributeUpdateResponderService::class;
        $this->interfaceFqcn = AttributeUpdateResponderServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testExecute_WithEmptyAttributeIds_DoesNotDispatchEvent(): void
    {
        $mockEventManager = $this->getMockBuilder(ManagerInterface::class)
            ->getMock();
        $mockEventManager->expects($this->never())
            ->method('dispatch');

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('debug')
            ->with(
                'Method: {method}, Debug: {message}',
                [
                    'method' => 'Klevu\IndexingCategories\Service\AttributeUpdateResponderService::execute',
                    'message' => 'No attribute Ids provided for KLEVU_CATEGORY attribute update.',
                ],
            );

        $service = $this->instantiateTestObject([
            'eventManager' => $mockEventManager,
            'logger' => $mockLogger,
        ]);
        $service->execute([]);
    }

    public function testExecute_WithData_TriggersDispatchEvent(): void
    {
        $expectedData = [
            AttributeUpdate::ATTRIBUTE_IDS => [1, 2, 3],
            AttributeUpdate::STORE_IDS => [1, 2],
        ];

        $attributeUpdateFactory = $this->objectManager->get(AttributeUpdateInterfaceFactory::class);
        $attributeUpdate = $attributeUpdateFactory->create([
            'data' => array_merge(
                [AttributeUpdate::ATTRIBUTE_TYPE => 'KLEVU_CATEGORY'],
                $expectedData,
            ),
        ]);

        $mockEventManager = $this->getMockBuilder(ManagerInterface::class)
            ->getMock();
        $mockEventManager->expects($this->once())
            ->method('dispatch')
            ->with(
                'klevu_indexing_attribute_update',
                [
                    'attributeUpdate' => $attributeUpdate,
                ],
            );

        $data = [
            AttributeUpdate::ATTRIBUTE_IDS => [1, 2, 3],
            AttributeUpdate::STORE_IDS => [1, 2],
        ];

        $service = $this->instantiateTestObject([
            'eventManager' => $mockEventManager,
        ]);
        $service->execute($data);
    }

    public function testExecute_WithOnlyStoreIDs_TriggersDispatchEvent(): void
    {
        $data = [
            AttributeUpdate::ATTRIBUTE_IDS => [1, 2, 3],
        ];

        $attributeUpdateFactory = $this->objectManager->get(AttributeUpdateInterfaceFactory::class);
        $attributeUpdate = $attributeUpdateFactory->create([
            'data' => array_merge(
                [
                    AttributeUpdate::ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
                    AttributeUpdate::STORE_IDS => [],
                ],
                $data,
            ),
        ]);

        $mockEventManager = $this->getMockBuilder(ManagerInterface::class)
            ->getMock();
        $mockEventManager->expects($this->once())
            ->method('dispatch')
            ->with(
                'klevu_indexing_attribute_update',
                [
                    'attributeUpdate' => $attributeUpdate,
                ],
            );

        $service = $this->instantiateTestObject([
            'eventManager' => $mockEventManager,
        ]);
        $service->execute($data);
    }

    /**
     * @testWith ["attributeType", "attribute_type"]
     *           ["storeIds", "stores"]
     */
    public function testExecute_HandlesException(string $key, string $invalidKey): void
    {
        $mockEventManager = $this->getMockBuilder(ManagerInterface::class)
            ->getMock();
        $mockEventManager->expects($this->never())
            ->method('dispatch');

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    'method' => 'Klevu\IndexingCategories\Service\AttributeUpdateResponderService::execute',
                    'message' => sprintf(
                        'Invalid key provided in creation of %s. Key %s',
                        AttributeUpdate::class,
                        $invalidKey,
                    ),
                ],
            );

        $exception = new \InvalidArgumentException(
            sprintf(
                'Invalid key provided in creation of %s. Key %s',
                AttributeUpdate::class,
                $invalidKey,
            ),
        );
        $mockAttributeUpdateFactory = $this->getMockBuilder(AttributeUpdateInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAttributeUpdateFactory->expects($this->once())
            ->method('create')
            ->willThrowException($exception);

        $data = [
            'attributeType' => 'KLEVU_CATEGORY',
            'attributeIds' => [1, 2, 3],
            'storeIds' => [1, 2],
        ];
        $data[$invalidKey] = $data[$key];
        unset($data[$key]);

        $service = $this->instantiateTestObject([
            'eventManager' => $mockEventManager,
            'attributeUpdateFactory' => $mockAttributeUpdateFactory,
            'logger' => $mockLogger,
        ]);
        $service->execute($data);
    }
}
