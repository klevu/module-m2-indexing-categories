<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Service\Provider;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Service\Provider\EntityDiscoveryProvider;
use Klevu\IndexingApi\Model\MagentoEntityInterface;
use Klevu\IndexingApi\Service\Provider\EntityDiscoveryProviderInterface;
use Klevu\IndexingCategories\Service\Provider\EntityDiscoveryProvider as EntityDiscoveryProviderVirtualType;
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\CategoryFixturePool;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers \Klevu\Indexing\Service\Provider\EntityDiscoveryProvider::class
 * @method EntityDiscoveryProviderInterface instantiateTestObject(?array $arguments = null)
 * @method EntityDiscoveryProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityDiscoveryProviderTest extends TestCase
{
    use CategoryTrait;
    use ObjectInstantiationTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use WebsiteTrait;

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

        $this->implementationFqcn = EntityDiscoveryProviderVirtualType::class; // @phpstan-ignore-line
        $this->interfaceFqcn = EntityDiscoveryProviderInterface::class;
        $this->implementationForVirtualType = EntityDiscoveryProvider::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->websiteFixturesPool = $this->objectManager->get(WebsiteFixturesPool::class);
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->categoryFixturePool = $this->objectManager->get(CategoryFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->categoryFixturePool->rollback();
        $this->storeFixturesPool->rollback();
        $this->websiteFixturesPool->rollback();
    }

    public function testGetEntityType_ReturnsCorrectString(): void
    {
        $provider = $this->instantiateTestObject();
        $this->assertSame(
            expected: 'KLEVU_CATEGORY',
            actual: $provider->getEntityType(),
            message: 'Get Entity Type',
        );
    }

    /**
     * @magentoDataFixtureBeforeTransaction Magento/Catalog/_files/enable_reindex_schedule.php
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_categories 0
     */
    public function testGetData_IsIndexableChecksDisabled(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());

        $this->setAuthKeys(
            $scopeProvider,
            $apiKey,
            'rest-auth-key',
        );

        $this->createCategory([
            'store_id' => $storeFixture->getId(),
        ]);
        $category = $this->categoryFixturePool->get('test_category');

        $provider = $this->instantiateTestObject();
        $result = $provider->getData(apiKeys: [$apiKey]);
        $categoryEntitiesByApiKey = iterator_to_array($result);

        $this->assertCount(
            expectedCount: 1,
            haystack: $categoryEntitiesByApiKey,
            message: 'Number of category collection by API Key',
        );
        $categoryEntities = iterator_to_array($categoryEntitiesByApiKey[$apiKey]);

        $filteredCategoryEntities = array_filter(
            array: $categoryEntities[0] ?? [],
            callback: static function (MagentoEntityInterface $categoryEntity) use ($category): bool {
                return (int)$categoryEntity->getEntityId() === (int)$category->getId();
            },
        );
        $categoryEntity = array_shift($filteredCategoryEntities);

        $this->assertSame(
            expected: (int)$category->getId(),
            actual: $categoryEntity->getEntityId(),
            message: 'Category ID',
        );
        $this->assertSame(
            expected: $apiKey,
            actual: $categoryEntity->getApiKey(),
            message: 'API Key',
        );
        $this->assertTrue(
            condition: $categoryEntity->isIndexable(),
            message: ' Is Indexable',
        );
    }

    /**
     * @magentoDataFixtureBeforeTransaction Magento/Catalog/_files/enable_reindex_schedule.php
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testGetData_IsIndexableChecksEnabled(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'rest-auth-key',
        );

        ConfigFixture::setGlobal(
            path: 'klevu/indexing/exclude_disabled_categories',
            value: 1,
        );

        $this->createCategory([
            'store_id' => $storeFixture->getId(),
            'is_active' => true,
            'key' => 'test_category_1',
        ]);
        $category1 = $this->categoryFixturePool->get('test_category_1');
        $this->createCategory([
            'store_id' => $storeFixture->getId(),
            'is_active' => false,
            'key' => 'test_category_2',
        ]);
        $category2 = $this->categoryFixturePool->get('test_category_2');

        $provider = $this->instantiateTestObject();
        $result = $provider->getData(apiKeys: [$apiKey]);
        $categoryEntitiesByApiKey = iterator_to_array($result);

        $this->assertCount(
            expectedCount: 1,
            haystack: $categoryEntitiesByApiKey,
            message: 'Number of category collection by API Key',
        );
        $categoryEntities = iterator_to_array($categoryEntitiesByApiKey[$apiKey]);

        $filteredCategoryEntities1 = array_filter(
            array: $categoryEntities[0] ?? [],
            callback: static function (MagentoEntityInterface $categoryEntity) use ($category1): bool {
                return (int)$categoryEntity->getEntityId() === (int)$category1->getId();
            },
        );
        $categoryEntity1 = array_shift($filteredCategoryEntities1);
        $this->assertSame(
            expected: (int)$category1->getId(),
            actual: $categoryEntity1->getEntityId(),
            message: 'Category ID',
        );
        $this->assertSame(
            expected: $apiKey,
            actual: $categoryEntity1->getApiKey(),
            message: 'API Key',
        );
        $this->assertTrue(
            condition: $categoryEntity1->isIndexable(),
            message: ' Is Indexable',
        );

        $filteredCategoryEntities2 = array_filter(
            array: $categoryEntities[0] ?? [],
            callback: static function (MagentoEntityInterface $categoryEntity) use ($category2): bool {
                return (int)$categoryEntity->getEntityId() === (int)$category2->getId();
            },
        );
        $categoryEntity2 = array_shift($filteredCategoryEntities2);
        $this->assertSame(
            expected: (int)$category2->getId(),
            actual: $categoryEntity2->getEntityId(),
            message: 'Category ID',
        );
        $this->assertSame(
            expected: $apiKey,
            actual: $categoryEntity2->getApiKey(),
            message: 'API Key',
        );
        $this->assertFalse(
            condition: $categoryEntity2->isIndexable(),
            message: ' Is Indexable',
        );
    }

    /**
     * @magentoDataFixtureBeforeTransaction Magento/Catalog/_files/enable_reindex_schedule.php
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testGetData_IsIndexable_ForCategoryDisabledInOneStore_IsIndexableChecksEnabled(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createWebsite();
        $websiteFixture = $this->websiteFixturesPool->get('test_website');

        $this->createStore([
            'code' => 'klevu_test_store_1',
            'key' => 'test_store_1',
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $store1 = $storeFixture1->get();
        $scopeProvider1 = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider1->setCurrentScope(scope: $store1);
        $this->setAuthKeys(
            scopeProvider: $scopeProvider1,
            jsApiKey: $apiKey,
            restAuthKey: 'rest-auth-key',
        );

        $this->createStore([
            'website_id' => $websiteFixture->getId(),
            'code' => 'klevu_test_store_2',
            'key' => 'test_store_2',
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');
        $store2 = $storeFixture2->get();
        $scopeProvider2 = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider2->setCurrentScope(scope: $store2);
        $this->setAuthKeys(
            scopeProvider: $scopeProvider2,
            jsApiKey: $apiKey,
            restAuthKey: 'rest-auth-key',
            removeApiKeys: false,
        );

        ConfigFixture::setGlobal(
            path: 'klevu/indexing/exclude_disabled_categories',
            value: 1,
        );

        $this->createCategory([
            'store_id' => $storeFixture1->getId(),
            'is_active' => true,
            'key' => 'test_category_1',
            'stores' => [
                $store1->getId() => [
                    'is_active' => true,
                ],
                $store2->getId() => [
                    'is_active' => false,
                ],
            ],
        ]);
        $category1 = $this->categoryFixturePool->get('test_category_1');

        $this->createCategory([
            'store_id' => $storeFixture1->getId(),
            'is_active' => false,
            'key' => 'test_category_2',
            'stores' => [
                $store1->getId() => [
                    'is_active' => false,
                ],
                $store2->getId() => [
                    'is_active' => false,
                ],
            ],
        ]);
        $category2 = $this->categoryFixturePool->get('test_category_2');

        $provider = $this->instantiateTestObject();
        $result = $provider->getData(apiKeys: [$apiKey]);
        $categoryEntitiesByApiKey = iterator_to_array($result);

        $this->assertCount(
            expectedCount: 1,
            haystack: $categoryEntitiesByApiKey,
            message: 'Number of category collection by API Key',
        );
        $categoryEntities = iterator_to_array($categoryEntitiesByApiKey[$apiKey]);

        $filteredCategoryEntities1 = array_filter(
            array: $categoryEntities[0] ?? [],
            callback: static function (MagentoEntityInterface $categoryEntity) use ($category1): bool {
                return (int)$categoryEntity->getEntityId() === (int)$category1->getId();
            },
        );
        $categoryEntity1 = array_shift($filteredCategoryEntities1);
        $this->assertSame(
            expected: (int)$category1->getId(),
            actual: $categoryEntity1->getEntityId(),
            message: 'Category ID',
        );
        $this->assertSame(
            expected: $apiKey,
            actual: $categoryEntity1->getApiKey(),
            message: 'API Key',
        );
        $this->assertTrue(
            condition: $categoryEntity1->isIndexable(),
            message: ' Is Indexable',
        );

        $filteredCategoryEntities2 = array_filter(
            array: $categoryEntities[0] ?? [],
            callback: static function (MagentoEntityInterface $categoryEntity) use ($category2): bool {
                return (int)$categoryEntity->getEntityId() === (int)$category2->getId();
            },
        );
        $categoryEntity2 = array_shift($filteredCategoryEntities2);
        $this->assertSame(
            expected: (int)$category2->getId(),
            actual: $categoryEntity2->getEntityId(),
            message: 'Category ID',
        );
        $this->assertSame(
            expected: $apiKey,
            actual: $categoryEntity2->getApiKey(),
            message: 'API Key',
        );
        $this->assertFalse(
            condition: $categoryEntity2->isIndexable(),
            message: ' Is Indexable',
        );
    }
}
