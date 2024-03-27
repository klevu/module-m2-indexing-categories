<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Service\Mapper;

use Klevu\Indexing\Service\Mapper\MagentoToKlevuAttributeMapper;
use Klevu\IndexingApi\Service\Mapper\MagentoToKlevuAttributeMapperInterface;
use Klevu\IndexingCategories\Service\Mapper\MagentoToKlevuAttributeMapper as MagentoToKlevuAttributeMapperVirtualType;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\CategoryAttributeInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers MagentoToKlevuAttributeMapper
 * @method MagentoToKlevuAttributeMapperInterface instantiateTestObject(?array $arguments = null)
 * @method MagentoToKlevuAttributeMapperInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class MagentoToKlevuAttributeMapperTest extends TestCase
{
    use AttributeTrait;
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

        $this->implementationFqcn = MagentoToKlevuAttributeMapperVirtualType::class;
        $this->interfaceFqcn = MagentoToKlevuAttributeMapperInterface::class;
        $this->implementationForVirtualType = MagentoToKlevuAttributeMapper::class;
        $this->objectManager = Bootstrap::getObjectManager();
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
    }

    public function testGet_ReturnsOriginalAttributeCode_WhenNoMappingSetUp(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
            'entity_type' => CategoryAttributeInterface::ENTITY_TYPE_CODE,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject();
        $result = $mapper->get($magentoAttribute);

        $this->assertSame(expected: 'cat__' . $magentoAttribute->getAttributeCode(), actual: $result);
    }

    public function testGet_ReturnsNewAttributeCode_WhenMappingSetUp(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
            'entity_type' => CategoryAttributeInterface::ENTITY_TYPE_CODE,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject([
            'attributeMapping' => [
                'klevu_test_attribute' => 'another_name',
            ],
        ]);
        $result = $mapper->get($magentoAttribute);

        $this->assertSame(expected: 'another_name', actual: $result);
    }

    public function testReverse_ReturnsOriginalAttributeCode_WhenNoMappingSetUp(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
            'entity_type' => CategoryAttributeInterface::ENTITY_TYPE_CODE,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject();
        $result = $mapper->reverse('cat__' . $magentoAttribute->getAttributeCode());

        $this->assertSame(expected: $magentoAttribute->getAttributeId(), actual: $result->getAttributeId());
    }

    public function testReverse_ReturnsNewAttributeCode_WhenMappingSetUp(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
            'entity_type' => CategoryAttributeInterface::ENTITY_TYPE_CODE,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject([
            'attributeMapping' => [
                'klevu_test_attribute' => 'another_name',
            ],
        ]);
        $result = $mapper->reverse('another_name');

        $this->assertSame(expected: $magentoAttribute->getAttributeId(), actual: $result->getAttributeId());
    }

    public function testReverse_ThrowsException_WhenAttributeNameIsNotMappedAndDoesNotExist(): void
    {
        $this->expectException(NoSuchEntityException::class);

        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
            'entity_type' => CategoryAttributeInterface::ENTITY_TYPE_CODE,
        ]);

        $mapper = $this->instantiateTestObject();
        $mapper->reverse('_IH*£END');
    }

    public function testGetByCode_ReturnsOriginalAttributeCode_WhenNoMappingSetUp(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
            'entity_type' => CategoryAttributeInterface::ENTITY_TYPE_CODE,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject();
        $result = $mapper->getByCode($magentoAttribute->getAttributeCode());

        $this->assertSame(expected: 'cat__' . $magentoAttribute->getAttributeCode(), actual: $result);
    }

    public function testGetByCode_ReturnsNewAttributeCode_WhenMappingSetUp(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
            'entity_type' => CategoryAttributeInterface::ENTITY_TYPE_CODE,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject([
            'attributeMapping' => [
                'klevu_test_attribute' => 'another_name',
            ],
        ]);
        $result = $mapper->getByCode($magentoAttribute->getAttributeCode());

        $this->assertSame(expected: 'another_name', actual: $result);
    }

    public function testReverseForCode_ReturnsOriginalAttribute_WhenNoMappingSetUp(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
            'entity_type' => CategoryAttributeInterface::ENTITY_TYPE_CODE,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject();
        $result = $mapper->reverseForCode('cat__' . $magentoAttribute->getAttributeCode());

        $this->assertSame(expected: $magentoAttribute->getAttributeCode(), actual: $result);
    }

    public function testReverseForCode_ReturnsNewAttributeCode_WhenMappingSetUp(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
            'entity_type' => CategoryAttributeInterface::ENTITY_TYPE_CODE,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject([
            'attributeMapping' => [
                'klevu_test_attribute' => 'another_name',
            ],
        ]);
        $result = $mapper->reverseForCode('another_name');

        $this->assertSame(expected: $magentoAttribute->getAttributeCode(), actual: $result);
    }
}
