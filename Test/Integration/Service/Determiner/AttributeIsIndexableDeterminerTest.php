<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Service\Determiner;

use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\Determiner\IsAttributeIndexableDeterminerInterface;
use Klevu\IndexingCategories\Service\Determiner\AttributeIsIndexableDeterminer;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\CategoryAttributeInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class AttributeIsIndexableDeterminerTest extends TestCase
{
    use AttributeTrait;
    use ObjectInstantiationTrait;
    use StoreTrait;
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

        $this->implementationFqcn = AttributeIsIndexableDeterminer::class;
        $this->interfaceFqcn = IsAttributeIndexableDeterminerInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->attributeFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    public function testExecute_ThrowsInvalidArgumentException_ForProductAttributeInterface(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $invalidEntity = $this->objectManager->create(ProductAttributeInterface::class);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Invalid Attribute: Invalid argument provided. %s must be of type %s.',
                AttributeInterface::class,
                CategoryAttributeInterface::ENTITY_TYPE_CODE,
            ),
        );

        $service = $this->instantiateTestObject();
        $service->execute(
            attribute: $invalidEntity,
            store: $storeFixture->get(),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @dataProvider dataProvider_testExecute_ReturnsBoolean_BasedOnIndexType
     */
    public function testExecute_ReturnsBoolean_BasedOnIndexType(IndexType $indexAs, bool $expected): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $this->createAttribute([
            'attribute_type' => 'text',
            'index_as' => $indexAs,
            'entity_type' => CategoryAttributeInterface::ENTITY_TYPE_CODE,
        ]);
        $attribute = $this->attributeFixturePool->get('test_attribute');

        $determiner = $this->instantiateTestObject();
        $isIndexable = $determiner->execute(
            attribute: $attribute->getAttribute(),
            store: $storeFixture->get(),
        );

        $this->assertSame(expected: $expected, actual: $isIndexable);
    }

    /**
     * @return mixed[][]
     */
    public function dataProvider_testExecute_ReturnsBoolean_BasedOnIndexType(): array
    {
        return [
            [IndexType::NO_INDEX, false],
            [IndexType::INDEX, true],
        ];
    }
}
