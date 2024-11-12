<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Service;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity\Collection;
use Klevu\Indexing\Service\EntityDiscoveryOrchestratorService;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\IndexingEntityRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\GroupRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\CategoryFixture;
use TddWizard\Fixtures\Catalog\CategoryFixturePool;

/**
 * @covers \Klevu\Indexing\Service\EntityDiscoveryOrchestratorService::class
 * @method EntityDiscoveryOrchestratorServiceInterface instantiateTestObject(?array $arguments = null)
 * @method EntityDiscoveryOrchestratorServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityDiscoveryOrchestratorServiceTest extends TestCase
{
    use CategoryTrait;
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;
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

        $this->implementationFqcn = EntityDiscoveryOrchestratorService::class;
        $this->interfaceFqcn = EntityDiscoveryOrchestratorServiceInterface::class;
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

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_categories 0
     */
    public function testExecute_AddsNewCategories_AsIndexable_WhenExcludeChecksDisabled(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $categoryCollectionCount = count($this->getCategories($store));

        $this->createCategory(
            categoryData: [
                'is_active' => false,
                'stores' => [
                    $store->getId() => [
                        'is_active' => false,
                    ],
                ],
            ],
        );
        $category = $this->categoryFixturePool->get('test_category');

        $service = $this->instantiateTestObject();
        $result = $service->execute(entityTypes: ['KLEVU_CATEGORY'], apiKeys: [$apiKey]);
        $this->assertTrue($result->isSuccess());

        $indexingEntities = $this->getCategoryIndexingEntities(apiKey: $apiKey);
        $this->assertCount(
            expectedCount: 1 + $categoryCollectionCount,
            haystack: $indexingEntities,
            message: 'Final Items Count',
        );

        $this->assertAddIndexingEntity($indexingEntities, $category, $apiKey, true);

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_categories 1
     */
    public function testExecute_AddsNewDisabledCategories_AsNotIndexable_WhenExcludeEnabled(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $categoryCollectionCount = count($this->getCategories($store));

        $this->createCategory(
            categoryData: [
                'key' => 'test_category_1',
                'is_active' => false,
                'stores' => [
                    $store->getId() => [
                        'is_active' => true,
                    ],
                ],
            ],
        );
        $category1 = $this->categoryFixturePool->get('test_category_1');
        $this->createCategory(
            categoryData: [
                'key' => 'test_category_2',
                'is_active' => true,
                'stores' => [
                    $store->getId() => [
                        'is_active' => false,
                    ],
                ],
            ],
        );
        $category2 = $this->categoryFixturePool->get('test_category_2');

        $service = $this->instantiateTestObject();
        $result = $service->execute(entityTypes: ['KLEVU_CATEGORY'], apiKeys: [$apiKey]);
        $this->assertTrue($result->isSuccess());

        $indexingEntities = $this->getCategoryIndexingEntities(apiKey: $apiKey);
        $this->assertCount(
            expectedCount: 2 + $categoryCollectionCount,
            haystack: $indexingEntities,
            message: 'Final Items Count',
        );

        $this->assertAddIndexingEntity($indexingEntities, $category1, $apiKey, true);
        $this->assertAddIndexingEntity($indexingEntities, $category2, $apiKey, false);

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key-1
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key-1
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key-2
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key-2
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_categories 1
     */
    public function testExecute_HandlesMultipleStores_DifferentKeys(): void
    {
        $apiKey1 = 'klevu-js-api-key-1';
        $apiKey2 = 'klevu-js-api-key-2';
        $this->cleanIndexingEntities(apiKey: $apiKey1);
        $this->cleanIndexingEntities(apiKey: $apiKey2);

        $this->createStore([
            'code' => 'klevu_test_store_1',
            'key' => 'test_store_1',
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store_1');
        $store1 = $storeFixture->get();

        $this->createStore([
            'code' => 'klevu_test_store_2',
            'key' => 'test_store_2',
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store_2');
        $store2 = $storeFixture->get();

        $categoryCollectionCount1 = count($this->getCategories($store1));
        $categoryCollectionCount2 = count($this->getCategories($store2));

        $this->createCategory(
            categoryData: [
                'key' => 'test_category_1',
                'is_active' => false,
                'stores' => [
                    $store1->getId() => [
                        'is_active' => true,
                    ],
                    $store2->getId() => [
                        'is_active' => false,
                    ],
                ],
            ],
        );
        $category1 = $this->categoryFixturePool->get('test_category_1');
        $this->createCategory(
            categoryData: [
                'key' => 'test_category_2',
                'is_active' => false,
                'stores' => [
                    $store1->getId() => [
                        'is_active' => false,
                    ],
                    $store2->getId() => [
                        'is_active' => true,
                    ],
                ],
            ],
        );
        $category2 = $this->categoryFixturePool->get('test_category_2');

        $service = $this->instantiateTestObject();
        $result1 = $service->execute(entityTypes: ['KLEVU_CATEGORY'], apiKeys: [$apiKey1]);
        $this->assertTrue($result1->isSuccess());

        $indexingEntities1 = $this->getCategoryIndexingEntities($apiKey1);
        $this->assertCount(
            expectedCount: 2 + $categoryCollectionCount1,
            haystack: $indexingEntities1,
            message: 'Final Items Count',
        );
        $this->assertAddIndexingEntity($indexingEntities1, $category1, $apiKey1, true);
        $this->assertAddIndexingEntity($indexingEntities1, $category2, $apiKey1, false);
        $this->cleanIndexingEntities(apiKey: $apiKey1);

        $result2 = $service->execute(entityTypes: ['KLEVU_CATEGORY'], apiKeys: [$apiKey2]);
        $this->assertTrue($result2->isSuccess());
        $indexingEntities2 = $this->getCategoryIndexingEntities($apiKey2);
        $this->assertCount(
            expectedCount: 2 + $categoryCollectionCount2,
            haystack: $indexingEntities2,
            message: 'Final Items Count',
        );
        $this->assertAddIndexingEntity($indexingEntities2, $category1, $apiKey2, false);
        $this->assertAddIndexingEntity($indexingEntities2, $category2, $apiKey2, true);

        $this->cleanIndexingEntities(apiKey: $apiKey2);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_categories 1
     */
    public function testExecute_HandlesMultipleStores_SameKeys(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore([
            'code' => 'klevu_test_store_1',
            'key' => 'test_store_1',
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store_1');
        $store1 = $storeFixture->get();

        $this->createStore([
            'code' => 'klevu_test_store_2',
            'key' => 'test_store_2',
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store_2');
        $store2 = $storeFixture->get();

        $this->createCategory(
            categoryData: [
                'key' => 'test_category_1',
                'is_active' => false,
                'stores' => [
                    $store1->getId() => [
                        'is_active' => true,
                    ],
                    $store2->getId() => [
                        'is_active' => false,
                    ],
                ],
            ],
        );
        $category1 = $this->categoryFixturePool->get('test_category_1');
        $this->createCategory(
            categoryData: [
                'key' => 'test_category_2',
                'is_active' => false,
                'stores' => [
                    $store1->getId() => [
                        'is_active' => false,
                    ],
                    $store2->getId() => [
                        'is_active' => false,
                    ],
                ],
            ],
        );
        $category2 = $this->categoryFixturePool->get('test_category_2');

        $this->cleanIndexingEntities(apiKey: $apiKey);

        $service = $this->instantiateTestObject();
        $result1 = $service->execute(entityTypes: ['KLEVU_CATEGORY'], apiKeys: [$apiKey]);

        $this->assertTrue($result1->isSuccess());
        $indexingEntities1 = $this->getCategoryIndexingEntities($apiKey);

        $this->assertAddIndexingEntity($indexingEntities1, $category1, $apiKey, true);
        $this->assertAddIndexingEntity($indexingEntities1, $category2, $apiKey, false);
        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_categories 1
     */
    public function testExecute_SetsExistingIndexableCategoryForDeletion(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $categoryCollectionCount = count($this->getCategories($store));

        $this->createCategory(
            categoryData: [
                'key' => 'test_category_1',
                'is_active' => false,
                'stores' => [
                    $store->getId() => [
                        'is_active' => false,
                    ],
                ],
            ],
        );
        $category = $this->categoryFixturePool->get('test_category_1');
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => (int)$category->getId(),
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);

        $service = $this->instantiateTestObject();
        $result1 = $service->execute(entityTypes: ['KLEVU_CATEGORY'], apiKeys: [$apiKey]);

        $this->assertTrue($result1->isSuccess());
        $indexingEntities1 = $this->getCategoryIndexingEntities($apiKey);
        $this->assertCount(
            expectedCount: 1 + $categoryCollectionCount,
            haystack: $indexingEntities1,
            message: 'Final Items Count',
        );
        $this->assertDeleteIndexingEntity($indexingEntities1, $category, $apiKey, Actions::DELETE, true);
        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_categories 1
     */
    public function testExecute_SetsExistingIndexableCategoryForDeletion_ForMultiStore(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createWebsite();
        $websiteFixture = $this->websiteFixturesPool->get('test_website');

        $this->createStore([
            'code' => 'klevu_test_store_1',
            'key' => 'test_store_1',
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store_1');
        $store1 = $storeFixture->get();

        $this->createStore([
            'code' => 'klevu_test_store_2',
            'key' => 'test_store_2',
            'website_id' => $websiteFixture->getId(),
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store_2');
        $store2 = $storeFixture->get();

        $this->createCategory(
            categoryData: [
                'key' => 'test_category_1',
                'is_active' => false,
                'stores' => [
                    $store1->getId() => [
                        'is_active' => false,
                    ],
                    $store2->getId() => [
                        'is_active' => false,
                    ],
                ],
            ],
        );
        $this->createCategory(
            categoryData: [
                'key' => 'test_category_2',
                'is_active' => false,
                'stores' => [
                    $store1->getId() => [
                        'is_active' => true,
                    ],
                    $store2->getId() => [
                        'is_active' => false,
                    ],
                ],
            ],
        );
        $category1 = $this->categoryFixturePool->get('test_category_1');
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => (int)$category1->getId(),
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $category2 = $this->categoryFixturePool->get('test_category_2');
        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => (int)$category2->getId(),
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute(entityTypes: ['KLEVU_CATEGORY'], apiKeys: [$apiKey]);
        $this->assertTrue($result->isSuccess());

        $indexingEntities = $this->getCategoryIndexingEntities($apiKey);
        $this->assertDeleteIndexingEntity($indexingEntities, $category1, $apiKey, Actions::DELETE, true);
        $this->assertDeleteIndexingEntity($indexingEntities, $category2, $apiKey, Actions::NO_ACTION, true);

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_categories 1
     */
    public function testExecute_SetsExistingNonIndexedCategoryToNotIndexable_WhenDisabled(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $this->createCategory(
            categoryData: [
                'key' => 'test_category_1',
                'is_active' => false,
                'stores' => [
                    $store->getId() => [
                        'is_active' => false,
                    ],
                ],
            ],
        );
        $category = $this->categoryFixturePool->get('test_category_1');
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => (int)$category->getId(),
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION_TIMESTAMP => null,
        ]);

        $service = $this->instantiateTestObject();
        $result1 = $service->execute(entityTypes: ['KLEVU_CATEGORY'], apiKeys: [$apiKey]);

        $this->assertTrue($result1->isSuccess());
        $indexingEntities = $this->getCategoryIndexingEntities($apiKey);

        $indexingEntityArray = $this->filterIndexEntities($indexingEntities, $category->getId(), $apiKey);
        $indexingEntity = array_shift($indexingEntityArray);
        $this->assertSame(
            expected: (int)$category->getId(),
            actual: $indexingEntity->getTargetId(),
            message: 'Target Id',
        );
        $this->assertSame(
            expected: 'KLEVU_CATEGORY',
            actual: $indexingEntity->getTargetEntityType(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: $apiKey,
            actual: $indexingEntity->getApiKey(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity->getNextAction(),
            message: 'Next Action',
        );
        $this->assertNull(
            actual: $indexingEntity->getLastActionTimestamp(),
            message: 'Last Action Timestamp',
        );
        $this->assertNull(
            actual: $indexingEntity->getLockTimestamp(),
            message: 'Lock Timestamp',
        );
        $this->assertFalse(
            condition: $indexingEntity->getIsIndexable(),
            message: 'Is Indexable',
        );

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_categories 1
     */
    public function testExecute_SetsExistingNonIndexedCategoryToNotIndexable_WhenDisabled_ForMultiStore(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createWebsite();
        $websiteFixture = $this->websiteFixturesPool->get('test_website');

        $this->createStore([
            'code' => 'klevu_test_store_1',
            'key' => 'test_store_1',
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store_1');
        $store1 = $storeFixture->get();

        $this->createStore([
            'code' => 'klevu_test_store_2',
            'key' => 'test_store_2',
            'website_id' => $websiteFixture->getId(),
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store_2');
        $store2 = $storeFixture->get();

        $this->createCategory(
            categoryData: [
                'key' => 'test_category_1',
                'is_active' => false,
                'stores' => [
                    $store1->getId() => [
                        'is_active' => false,
                    ],
                    $store2->getId() => [
                        'is_active' => false,
                    ],
                ],
            ],
        );
        $this->createCategory(
            categoryData: [
                'key' => 'test_category_2',
                'is_active' => false,
                'stores' => [
                    $store1->getId() => [
                        'is_active' => true,
                    ],
                    $store2->getId() => [
                        'is_active' => false,
                    ],
                ],
            ],
        );
        $category1 = $this->categoryFixturePool->get('test_category_1');
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => (int)$category1->getId(),
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION_TIMESTAMP => null,
        ]);
        $category2 = $this->categoryFixturePool->get('test_category_2');
        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => (int)$category2->getId(),
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION_TIMESTAMP => null,
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute(entityTypes: ['KLEVU_CATEGORY'], apiKeys: [$apiKey]);
        $this->assertTrue($result->isSuccess());

        $indexingEntities = $this->getCategoryIndexingEntities($apiKey);

        $indexingEntityArray1 = $this->filterIndexEntities($indexingEntities, $category1->getId(), $apiKey);
        $indexingEntity1 = array_shift($indexingEntityArray1);
        $this->assertSame(
            expected: (int)$category1->getId(),
            actual: $indexingEntity1->getTargetId(),
            message: 'Target Id',
        );
        $this->assertSame(
            expected: 'KLEVU_CATEGORY',
            actual: $indexingEntity1->getTargetEntityType(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: $apiKey,
            actual: $indexingEntity1->getApiKey(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity1->getNextAction(),
            message: 'Next Action',
        );
        $this->assertNull(
            actual: $indexingEntity1->getLastActionTimestamp(),
            message: 'Last Action Timestamp',
        );
        $this->assertNull(
            actual: $indexingEntity1->getLockTimestamp(),
            message: 'Lock Timestamp',
        );
        $this->assertFalse(
            condition: $indexingEntity1->getIsIndexable(),
            message: 'Is Indexable',
        );

        $indexingEntityArray2 = $this->filterIndexEntities($indexingEntities, $category2->getId(), $apiKey);
        $indexingEntity2 = array_shift($indexingEntityArray2);
        $this->assertSame(
            expected: (int)$category2->getId(),
            actual: $indexingEntity2->getTargetId(),
            message: 'Target Id',
        );
        $this->assertSame(
            expected: 'KLEVU_CATEGORY',
            actual: $indexingEntity2->getTargetEntityType(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: $apiKey,
            actual: $indexingEntity2->getApiKey(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: Actions::ADD,
            actual: $indexingEntity2->getNextAction(),
            message: 'Next Action',
        );
        $this->assertNull(
            actual: $indexingEntity2->getLastActionTimestamp(),
            message: 'Last Action Timestamp',
        );
        $this->assertNull(
            actual: $indexingEntity2->getLockTimestamp(),
            message: 'Lock Timestamp',
        );
        $this->assertTrue(
            condition: $indexingEntity2->getIsIndexable(),
            message: 'Is Indexable',
        );

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_categories 1
     */
    public function testExecute_SkipsExistingNonIndexableProduct_WhenSetToNotIndexable(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();

        $this->createCategory(
            categoryData: [
                'key' => 'test_category_1',
                'is_active' => false,
            ],
        );
        $category = $this->categoryFixturePool->get('test_category_1');
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => (int)$category->getId(),
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute(entityTypes: ['KLEVU_CATEGORY'], apiKeys: [$apiKey]);
        $this->assertTrue($result->isSuccess());

        $indexingEntities = $this->getCategoryIndexingEntities($apiKey);
        $this->assertDeleteIndexingEntity($indexingEntities, $category, $apiKey, Actions::NO_ACTION, false);
        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_categories 1
     */
    public function testExecute_SetsExistingCategoryToIndexable_WhenEnabled_IfPreviousDeleteActionNotYetIndexed(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();

        $this->createCategory(
            categoryData: [
                'key' => 'test_category_1',
                'is_active' => true,
            ],
        );
        $category = $this->categoryFixturePool->get('test_category_1');
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => (int)$category->getId(),
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute(entityTypes: ['KLEVU_CATEGORY'], apiKeys: [$apiKey]);
        $this->assertTrue($result->isSuccess());

        $indexingEntities = $this->getCategoryIndexingEntities($apiKey);
        $indexingEntityArray = $this->filterIndexEntities($indexingEntities, $category->getId(), $apiKey);
        $indexingEntity = array_shift($indexingEntityArray);
        $this->assertSame(
            expected: (int)$category->getId(),
            actual: $indexingEntity->getTargetId(),
            message: 'Target Id',
        );
        $this->assertSame(
            expected: 'KLEVU_CATEGORY',
            actual: $indexingEntity->getTargetEntityType(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: $apiKey,
            actual: $indexingEntity->getApiKey(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity->getNextAction(),
            message: sprintf(
                'Next Action: Expected %s, Received %s',
                Actions::NO_ACTION->value,
                $indexingEntity->getNextAction()->value,
            ),
        );
        $this->assertNotNull(
            actual: $indexingEntity->getLastActionTimestamp(),
            message: 'Last Action Timestamp',
        );
        $this->assertNull(
            actual: $indexingEntity->getLockTimestamp(),
            message: 'Lock Timestamp',
        );
        $this->assertTrue(
            condition: $indexingEntity->getIsIndexable(),
            message: 'Is Indexable',
        );

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_categories 1
     */
    public function testExecute_SetsExistingCategoryToIndexable_WhenEnabled_IfPreviousDeleteActionNotYetIndexed_ForMultiStore(): void // phpcs:ignore Generic.Files.LineLength.TooLong
    {
        $apiKey = 'klevu-js-api-key';

        $this->createWebsite();
        $websiteFixture = $this->websiteFixturesPool->get('test_website');

        $this->createStore([
            'code' => 'klevu_test_store_1',
            'key' => 'test_store_1',
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store_1');
        $store1 = $storeFixture->get();

        $this->createStore([
            'code' => 'klevu_test_store_2',
            'key' => 'test_store_2',
            'website_id' => $websiteFixture->getId(),
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store_2');
        $store2 = $storeFixture->get();

        $this->createCategory(
            categoryData: [
                'key' => 'test_category_1',
                'is_active' => false,
                'stores' => [
                    $store1->getId() => [
                        'is_active' => false,
                    ],
                    $store2->getId() => [
                        'is_active' => true,
                    ],
                ],
            ],
        );
        $category1 = $this->categoryFixturePool->get('test_category_1');
        $this->createCategory(
            categoryData: [
                'key' => 'test_category_2',
                'is_active' => false,
                'stores' => [
                    $store1->getId() => [
                        'is_active' => true,
                    ],
                    $store2->getId() => [
                        'is_active' => false,
                    ],
                ],
            ],
        );
        $category2 = $this->categoryFixturePool->get('test_category_2');
        $this->createCategory(
            categoryData: [
                'key' => 'test_category_3',
                'is_active' => true,
            ],
        );
        $category3 = $this->categoryFixturePool->get('test_category_3');
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => (int)$category1->getId(),
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => (int)$category2->getId(),
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => (int)$category3->getId(),
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute(entityTypes: ['KLEVU_CATEGORY'], apiKeys: [$apiKey]);
        $this->assertTrue($result->isSuccess());

        $indexingEntities = $this->getCategoryIndexingEntities($apiKey);

        $indexingEntityArray1 = $this->filterIndexEntities($indexingEntities, $category1->getId(), $apiKey);
        $indexingEntity1 = array_shift($indexingEntityArray1);
        $this->assertSame(
            expected: (int)$category1->getId(),
            actual: $indexingEntity1->getTargetId(),
            message: 'Target Id',
        );
        $this->assertSame(
            expected: 'KLEVU_CATEGORY',
            actual: $indexingEntity1->getTargetEntityType(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: $apiKey,
            actual: $indexingEntity1->getApiKey(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: Actions::ADD,
            actual: $indexingEntity1->getNextAction(),
            message: 'Next Action',
        );
        $this->assertNotNull(
            actual: $indexingEntity1->getLastActionTimestamp(),
            message: 'Last Action Timestamp',
        );
        $this->assertNull(
            actual: $indexingEntity1->getLockTimestamp(),
            message: 'Lock Timestamp',
        );
        $this->assertTrue(
            condition: $indexingEntity1->getIsIndexable(),
            message: 'Is Indexable',
        );

        $indexingEntityArray2 = $this->filterIndexEntities($indexingEntities, $category2->getId(), $apiKey);
        $indexingEntity2 = array_shift($indexingEntityArray2);
        $this->assertSame(
            expected: (int)$category2->getId(),
            actual: $indexingEntity2->getTargetId(),
            message: 'Target Id',
        );
        $this->assertSame(
            expected: 'KLEVU_CATEGORY',
            actual: $indexingEntity2->getTargetEntityType(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: $apiKey,
            actual: $indexingEntity2->getApiKey(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity2->getNextAction(),
            message: 'Next Action',
        );
        $this->assertNotNull(
            actual: $indexingEntity2->getLastActionTimestamp(),
            message: 'Last Action Timestamp',
        );
        $this->assertNull(
            actual: $indexingEntity2->getLockTimestamp(),
            message: 'Lock Timestamp',
        );
        $this->assertTrue(
            condition: $indexingEntity2->getIsIndexable(),
            message: 'Is Indexable',
        );

        $indexingEntityArray3 = $this->filterIndexEntities($indexingEntities, $category3->getId(), $apiKey);
        $indexingEntity3 = array_shift($indexingEntityArray3);
        $this->assertSame(
            expected: (int)$category3->getId(),
            actual: $indexingEntity3->getTargetId(),
            message: 'Target Id',
        );
        $this->assertSame(
            expected: 'KLEVU_CATEGORY',
            actual: $indexingEntity3->getTargetEntityType(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: $apiKey,
            actual: $indexingEntity3->getApiKey(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity3->getNextAction(),
            message: 'Next Action',
        );
        $this->assertNotNull(
            actual: $indexingEntity3->getLastActionTimestamp(),
            message: 'Last Action Timestamp',
        );
        $this->assertNull(
            actual: $indexingEntity3->getLockTimestamp(),
            message: 'Lock Timestamp',
        );
        $this->assertTrue(
            condition: $indexingEntity3->getIsIndexable(),
            message: 'Is Indexable',
        );

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @param IndexingEntityInterface[] $indexingEntities
     * @param CategoryFixture $categoryFixture
     * @param string $apiKey
     * @param bool $isIndexable
     *
     * @return void
     */
    private function assertAddIndexingEntity(
        array $indexingEntities,
        CategoryFixture $categoryFixture,
        string $apiKey,
        bool $isIndexable,
    ): void {
        $indexingEntityArray = $this->filterIndexEntities($indexingEntities, $categoryFixture->getId(), $apiKey);
        $indexingEntity = array_shift($indexingEntityArray);
        $this->assertSame(
            expected: (int)$categoryFixture->getId(),
            actual: $indexingEntity->getTargetId(),
            message: 'Target Id',
        );
        $this->assertSame(
            expected: 'KLEVU_CATEGORY',
            actual: $indexingEntity->getTargetEntityType(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: $apiKey,
            actual: $indexingEntity->getApiKey(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: $isIndexable
                ? Actions::ADD
                : Actions::NO_ACTION,
            actual: $indexingEntity->getNextAction(),
            message: 'Next Action',
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity->getLastAction(),
            message: 'Last Action',
        );
        $this->assertNull(
            actual: $indexingEntity->getLastActionTimestamp(),
            message: 'Last Action Timestamp',
        );
        $this->assertNull(
            actual: $indexingEntity->getLockTimestamp(),
            message: 'Lock Timestamp',
        );
        $this->assertSame(
            expected: $isIndexable,
            actual: $indexingEntity->getIsIndexable(),
            message: 'Is Indexable',
        );
    }

    /**
     * @param IndexingEntityInterface[] $indexingEntities
     * @param CategoryFixture $categoryFixture
     * @param string $apiKey
     * @param Actions $nextAction
     * @param bool $isIndexable
     *
     * @return void
     */
    private function assertDeleteIndexingEntity(
        array $indexingEntities,
        CategoryFixture $categoryFixture,
        string $apiKey,
        Actions $nextAction = Actions::NO_ACTION,
        bool $isIndexable = true,
    ): void {
        $indexingEntityArray = $this->filterIndexEntities($indexingEntities, $categoryFixture->getId(), $apiKey);
        $indexingEntity = array_shift($indexingEntityArray);
        $this->assertSame(
            expected: (int)$categoryFixture->getId(),
            actual: $indexingEntity->getTargetId(),
            message: 'Target Id',
        );
        $this->assertSame(
            expected: 'KLEVU_CATEGORY',
            actual: $indexingEntity->getTargetEntityType(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: $apiKey,
            actual: $indexingEntity->getApiKey(),
            message: 'Target Entity Type',
        );
        $this->assertSame(
            expected: $nextAction,
            actual: $indexingEntity->getNextAction(),
            message: 'Next Action',
        );
        $this->assertNotNull(
            actual: $indexingEntity->getLastAction(),
            message: 'Last Action',
        );
        $this->assertNotNull(
            actual: $indexingEntity->getLastActionTimestamp(),
            message: 'Last Action Timestamp',
        );
        $this->assertNull(
            actual: $indexingEntity->getLockTimestamp(),
            message: 'Lock Timestamp',
        );
        $this->assertSame(
            expected: $isIndexable,
            actual: $indexingEntity->getIsIndexable(),
            message: 'Is Indexable',
        );
    }

    /**
     * @param IndexingEntityInterface[] $indexingEntities
     * @param int $entityId
     * @param string $apiKey
     *
     * @return IndexingEntityInterface[]
     */
    private function filterIndexEntities(array $indexingEntities, int $entityId, string $apiKey): array
    {
        return array_filter(
            array: $indexingEntities,
            callback: static function (IndexingEntityInterface $indexingEntity) use ($entityId, $apiKey) {
                return (int)$entityId === (int)$indexingEntity->getTargetId()
                    && $apiKey === $indexingEntity->getApiKey();
            },
        );
    }

    /**
     * @return IndexingEntityInterface[]
     */
    private function getCategoryIndexingEntities(?string $apiKey = null): array
    {
        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(
            field: IndexingEntity::TARGET_ENTITY_TYPE,
            condition: ['eq' => 'KLEVU_CATEGORY'],
        );
        if ($apiKey) {
            $collection->addFieldToFilter(
                field: IndexingEntity::API_KEY,
                condition: ['eq' => $apiKey],
            );
        }

        return $collection->getItems();
    }

    /**
     * @param StoreInterface|null $store
     *
     * @return CategoryInterface[]
     */
    private function getCategories(?StoreInterface $store = null): array
    {
        $categoryCollectionFactory = $this->objectManager->get(CategoryCollectionFactory::class);
        $categoryCollection = $categoryCollectionFactory->create();
        $categoryCollection->addAttributeToSelect('*');
        $categoryCollection->addFieldToFilter('path', ['neq' => '1']);
        if ($store) {
            $categoryCollection->setStore((int)$store->getId());
            $groupRepository = $this->objectManager->get(GroupRepositoryInterface::class);
            $group = $groupRepository->get($store->getStoreGroupId());
            $categoryCollection->addPathsFilter([Category::TREE_ROOT_ID . '/' . $group->getRootCategoryId() . '/']);
        }

        return $categoryCollection->getItems();
    }

    /**
     * @param mixed[] $data
     *
     * @return IndexingEntityInterface
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    private function createIndexingEntity(array $data): IndexingEntityInterface
    {
        $repository = $this->objectManager->get(IndexingEntityRepositoryInterface::class);
        $indexingEntity = $repository->create();
        $indexingEntity->setTargetId((int)$data[IndexingEntity::TARGET_ID]);
        $indexingEntity->setTargetParentId(null);
        $indexingEntity->setTargetEntityType($data[IndexingEntity::TARGET_ENTITY_TYPE] ?? 'KLEVU_CATEGORY');
        $indexingEntity->setApiKey($data[IndexingEntity::API_KEY] ?? 'klevu-js-api-key');
        $indexingEntity->setNextAction($data[IndexingEntity::NEXT_ACTION] ?? Actions::NO_ACTION);
        $indexingEntity->setLastAction($data[IndexingEntity::LAST_ACTION] ?? Actions::NO_ACTION);
        $indexingEntity->setLastActionTimestamp($data[IndexingEntity::LAST_ACTION_TIMESTAMP] ?? null);
        $indexingEntity->setLockTimestamp($data[IndexingEntity::LOCK_TIMESTAMP] ?? null);
        $indexingEntity->setIsIndexable($data[IndexingEntity::IS_INDEXABLE] ?? true);

        return $repository->save($indexingEntity);
    }
}
