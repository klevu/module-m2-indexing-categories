<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Service\Provider;

use Klevu\Indexing\Service\Provider\AttributeProvider;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\Provider\AttributeProviderInterface;
use Klevu\IndexingCategories\Service\Provider\CategoryAttributeProvider as CategoryAttributeProviderVirtualType;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\CategoryAttributeInterface;
use Magento\Catalog\Model\ResourceModel\Category\Attribute\Collection as CategoryAttributeCollection;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\IndexingCategories\Service\Provider\CategoryAttributeProvider::class
 * @method AttributeProviderInterface instantiateTestObject(?array $arguments = null)
 * @method AttributeProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class CategoryAttributeProviderTest extends TestCase
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

        $this->implementationFqcn = CategoryAttributeProviderVirtualType::class;
        $this->interfaceFqcn = AttributeProviderInterface::class;
        $this->implementationForVirtualType = AttributeProvider::class;
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

    /**
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsAttributeData(): void
    {
        $productAttributeCollection = $this->objectManager->get(CategoryAttributeCollection::class);
        $attributeCount = $productAttributeCollection->getSize();

        $this->createAttribute([
            'key' => 'test_attribute_1',
            'code' => 'klevu_test_attribute_1',
            'index_as' => IndexType::INDEX,
            'attribute_type' => 'textarea',
            'entity_type' => CategoryAttributeInterface::ENTITY_TYPE_CODE,
        ]);
        $attributeFixture1 = $this->attributeFixturePool->get('test_attribute_1');
        $this->createAttribute([
            'key' => 'test_attribute_2',
            'code' => 'klevu_test_attribute_2',
            'index_as' => IndexType::NO_INDEX,
            'attribute_type' => 'date',
            'entity_type' => CategoryAttributeInterface::ENTITY_TYPE_CODE,
        ]);
        $attributeFixture2 = $this->attributeFixturePool->get('test_attribute_2');

        $provider = $this->instantiateTestObject();
        $searchResults = $provider->get();

        /** @var AttributeInterface[] $items */
        $items = [];
        foreach ($searchResults as $searchResult) {
            $items[] = $searchResult;
        }

        $this->assertCount(expectedCount: 2 + $attributeCount, haystack: $items);
        $attributeIds = array_map(
            callback: static function (AttributeInterface $item): int {
                return (int)$item->getAttributeId();
            },
            array: $items,
        );
        $this->assertContains(needle: (int)$attributeFixture1->getAttributeId(), haystack: $attributeIds);
        $this->assertContains(needle: (int)$attributeFixture2->getAttributeId(), haystack: $attributeIds);

        $attribute1Array = array_filter(
            array: $items,
            callback: static function (AttributeInterface $attribute) use ($attributeFixture1): bool {
                return (int)$attribute->getAttributeId() === (int)$attributeFixture1->getAttributeId();
            },
        );
        /** @var AttributeInterface $attribute1 */
        $attribute1 = array_shift($attribute1Array);
        $this->assertSame(
            expected: 'klevu_test_attribute_1',
            actual: $attribute1->getAttributeCode(),
        );
        $this->assertSame(
            expected: IndexType::INDEX->value,
            actual: (int)$attribute1->getData(// @phpstan-ignore-line
                MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE,
            ),
        );

        $attribute2Array = array_filter(
            array: $items,
            callback: static function (AttributeInterface $attribute) use ($attributeFixture2): bool {
                return (int)$attribute->getAttributeId() === (int)$attributeFixture2->getAttributeId();
            },
        );
        /** @var AttributeInterface $attribute2 */
        $attribute2 = array_shift($attribute2Array);
        $this->assertSame(
            expected: 'klevu_test_attribute_2',
            actual: $attribute2->getAttributeCode(),
        );
        $this->assertSame(
            expected: IndexType::NO_INDEX->value,
            actual: (int)$attribute2->getData(// @phpstan-ignore-line
                MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE,
            ),
        );
    }
}
