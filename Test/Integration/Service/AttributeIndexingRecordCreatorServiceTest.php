<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Service;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\AttributeIndexingRecordCreatorServiceInterface;
use Klevu\IndexingCategories\Model\Source\Aspect;
use Klevu\IndexingCategories\Service\AttributeIndexingRecordCreatorService;
use Klevu\IndexingCategories\Service\Mapper\MagentoToKlevuAttributeMapper;
use Klevu\PhpSDK\Model\Indexing\DataType;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\CategoryAttributeInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers \Klevu\IndexingProducts\Service\AttributeIndexingRecordCreatorService::class
 * @method AttributeIndexingRecordCreatorServiceInterface instantiateTestObject(?array $arguments = null)
 * @method AttributeIndexingRecordCreatorServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AttributeIndexingRecordCreatorServiceTest extends TestCase
{
    use AttributeTrait;
    use ObjectInstantiationTrait;
    use SetAuthKeysTrait;
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

        $this->implementationFqcn = AttributeIndexingRecordCreatorService::class;
        $this->interfaceFqcn = AttributeIndexingRecordCreatorServiceInterface::class;
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
     * @magentoAppIsolation enabled
     * @magentoConfigFixture default/general/locale/code en_US
     * @magentoConfigFixture klevu_test_store_1_store general/locale/code en_GB
     * @magentoConfigFixture klevu_test_store_2_store general/locale/code fr_FR
     * @magentoConfigFixture klevu_test_store_3_store general/locale/code de_DE
     */
    public function testExecute_ReturnsAttributeInterface_ForTextAttribute(): void
    {
        $apiKey = 'Klevu-test-js-api-key';

        $this->createAttribute([
            'key' => 'klevu_test_text_attribute',
            'code' => 'klevu_test_text_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
            'attribute_type' => 'text',
            'entity_type' => CategoryAttributeInterface::ENTITY_TYPE_CODE,
            'data' => [
                'is_searchable' => true,
                'is_filterable' => false,
                'used_in_product_listing' => true,
            ],
            'index_as' => IndexType::INDEX,
            'aspect' => Aspect::ALL,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('klevu_test_text_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $service = $this->instantiateTestObject();
        $indexingAttribute = $service->execute($magentoAttribute, $apiKey);

        $this->assertSame(
            expected: 'cat__' . $magentoAttribute->getAttributeCode(),
            actual: $indexingAttribute->getAttributeName(),
        );
        $this->assertSame(
            expected: DataType::STRING->value,
            actual: $indexingAttribute->getDatatype(),
        );
        $label = $indexingAttribute->getLabel();
        $this->assertArrayHasKey(key: 'default', array: $label);
        $this->assertSame(expected: 'TEST TEXT ATTRIBUTE', actual: $label['default']);

        $this->assertTrue(condition: $indexingAttribute->isSearchable());
        $this->assertFalse(condition: $indexingAttribute->isFilterable());
        $this->assertTrue(condition: $indexingAttribute->isReturnable());
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_ReturnsAttributeInterface_ForBooleanAttribute(): void
    {
        $apiKey = 'klevu-js-api-key-1';

        $this->createStore([
            'key' => 'test_store_1',
            'code' => 'klevu_test_store_1',
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $scopeProvider1 = $this->objectManager->create(ScopeProviderInterface::class);
        $scopeProvider1->setCurrentScope(scope: $storeFixture1->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider1,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key-1',
        );
        ConfigFixture::setForStore(
            path: 'general/locale/code',
            value: 'en_GB',
            storeCode: 'klevu_test_store_1',
        );

        $this->createStore([
            'key' => 'test_store_2',
            'code' => 'klevu_test_store_2',
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');
        $scopeProvider2 = $this->objectManager->create(ScopeProviderInterface::class);
        $scopeProvider2->setCurrentScope(scope: $storeFixture2->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider2,
            jsApiKey: 'klevu-js-api-key-2',
            restAuthKey: 'klevu-rest-key-2',
            removeApiKeys: false,
        );
        ConfigFixture::setForStore(
            path: 'general/locale/code',
            value: 'fr_FR',
            storeCode: 'klevu_test_store_2',
        );

        $this->createStore([
            'key' => 'test_store_3',
            'code' => 'klevu_test_store_3',
        ]);
        $storeFixture3 = $this->storeFixturesPool->get('test_store_3');
        $scopeProvider3 = $this->objectManager->create(ScopeProviderInterface::class);
        $scopeProvider3->setCurrentScope(scope: $storeFixture3->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider3,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key-1',
            removeApiKeys: false,
        );
        ConfigFixture::setForStore(
            path: 'general/locale/code',
            value: 'de_DE',
            storeCode: 'klevu_test_store_3',
        );

        $this->createAttribute([
            'key' => 'klevu_test_boolean_attribute',
            'code' => 'klevu_test_boolean_attribute',
            'attribute_type' => 'boolean',
            'entity_type' => CategoryAttributeInterface::ENTITY_TYPE_CODE,
            'label' => 'TEST BOOLEAN ATTRIBUTE',
            'labels' => [
                $storeFixture2->getId() => 'Label Store 2',
                $storeFixture3->getId() => 'Label Store 3',
            ],
            'data' => [
                'is_searchable' => false,
                'is_filterable' => true,
                'used_in_product_listing' => false,
            ],
            'index_as' => IndexType::INDEX,
            'aspect' => Aspect::ALL,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('klevu_test_boolean_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $attributeCodeMapper = $this->objectManager->create(MagentoToKlevuAttributeMapper::class, [
            'attributeMapping' => [
                'klevu_test_boolean_attribute' => 'another_name',
            ],
        ]);

        $service = $this->instantiateTestObject([
            'attributeMapperService' => $attributeCodeMapper,
        ]);
        $indexingAttribute = $service->execute($magentoAttribute, $apiKey);

        $this->assertSame(
            expected: 'another_name',
            actual: $indexingAttribute->getAttributeName(),
        );
        $this->assertSame(
            expected: DataType::STRING->value,
            actual: $indexingAttribute->getDatatype(),
        );
        $this->assertFalse(condition: $indexingAttribute->isSearchable());
        $this->assertTrue(condition: $indexingAttribute->isFilterable());
        $this->assertFalse(condition: $indexingAttribute->isReturnable());

        $label = $indexingAttribute->getLabel();
        $this->assertCount(expectedCount: 2, haystack: $label);

        $this->assertArrayHasKey(key: 'default', array: $label);
        $this->assertSame(expected: 'TEST BOOLEAN ATTRIBUTE', actual: $label['default']);

        // No store label
        $this->assertArrayNotHasKey(key: 'en-GB-klevu_test_store_1', array: $label);

        // Integrated with a different API key
        $this->assertArrayNotHasKey(key: 'fr-FR-klevu_test_store_2', array: $label);

        $this->assertArrayHasKey(key: 'de-DE-klevu_test_store_3', array: $label);
        $this->assertSame(expected: 'Label Store 3', actual: $label['de-DE-klevu_test_store_3']);
    }
}
