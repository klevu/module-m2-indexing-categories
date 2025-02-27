<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Plugin\Catalog\Model\ResourceModel;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity\Collection as IndexingEntityCollection;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity\CollectionFactory as IndexingEntityCollectionFactory;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingCategories\Plugin\Catalog\Model\ResourceModel\CategoryPlugin;
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResourceModel;
use Magento\Framework\DataObject;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\CategoryFixturePool;

/**
 * @covers \Klevu\IndexingCategories\Plugin\Catalog\Model\ResourceModel\CategoryPlugin
 * @method CategoryPlugin instantiateTestObject(?array $arguments = null)
 * @method CategoryPlugin instantiateTestObjectFromInterface(?array $arguments = null)
 */
class CategoryPluginTest extends TestCase
{
    use CategoryTrait;
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use StoreTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null;
    /**
     * @var string|null
     */
    private ?string $pluginName = 'Klevu_IndexingCategories::CategoryResourceModelPlugin';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = CategoryPlugin::class;
        $this->objectManager = Bootstrap::getObjectManager();
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
    }

    /**
     * @magentoAppArea global
     */
    public function testPlugin_InterceptsCallsToTheField_InGlobalScope(): void
    {
        $pluginInfo = $this->getSystemConfigPluginInfo();
        $this->assertArrayHasKey($this->pluginName, $pluginInfo);
        $this->assertSame(CategoryPlugin::class, $pluginInfo[$this->pluginName]['instance']);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testAroundSave_ForNewCategories_setsNextActonAdd(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $this->createCategory([
            'store_id' => (int)$store->getId(),
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_category');
        $category = $categoryFixture->getCategory();

        $categoryIndexingEntity = $this->getIndexingEntityForCategory($apiKey, $category);

        $this->assertNotNull($categoryIndexingEntity);
        $this->assertSame(expected: Actions::ADD, actual: $categoryIndexingEntity->getNextAction());

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testAroundSave_ForExistingCategories_WhichHasNotYetBeenSynced_DoesNotChangeNextAction(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $this->createCategory([
            'store_id' => (int)$store->getId(),
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_category');
        /** @var Category&CategoryInterface $category */
        $category = $categoryFixture->getCategory();

        $categoryIndexingEntity = $this->getIndexingEntityForCategory($apiKey, $category);
        $this->assertNotNull($categoryIndexingEntity);
        $this->assertSame(expected: Actions::ADD, actual: $categoryIndexingEntity->getNextAction());

        $category->setDescription('Category Test: New Description');
        $categoryResourceModel = $this->objectManager->get(CategoryResourceModel::class);
        $categoryResourceModel->save($category);

        $categoryIndexingEntity = $this->getIndexingEntityForCategory($apiKey, $category);
        $this->assertNotNull($categoryIndexingEntity);
        $this->assertSame(expected: Actions::ADD, actual: $categoryIndexingEntity->getNextAction());

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testAroundSave_ForExistingCategory_UpdateNextAction(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $this->createCategory([
            'store_id' => (int)$store->getId(),
            'is_active' => true,
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_category');
        /** @var Category&CategoryInterface $category */
        $category = $categoryFixture->getCategory();

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::TARGET_ID => $category->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $category->setDescription('Category Test: New Description');
        $categoryResourceModel = $this->objectManager->get(CategoryResourceModel::class);
        $categoryResourceModel->save($category);

        $categoryIndexingEntity = $this->getIndexingEntityForCategory($apiKey, $category);
        $this->assertNotNull($categoryIndexingEntity);
        $this->assertTrue(condition: $categoryIndexingEntity->getIsIndexable());
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $categoryIndexingEntity->getNextAction(),
        );

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_categories 1
     */
    public function testAroundSave_ForExistingCategory_NotIndexable_DoesNotChangeNextAction(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $this->createCategory([
            'store_id' => (int)$store->getId(),
            'is_active' => false,
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_category');
        /** @var Category&CategoryInterface $category */
        $category = $categoryFixture->getCategory();

        $categoryIndexingEntity = $this->getIndexingEntityForCategory($apiKey, $category);
        $this->assertNotNull($categoryIndexingEntity);
        $this->assertFalse(condition: $categoryIndexingEntity->getIsIndexable());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $categoryIndexingEntity->getNextAction());

        $category->setTitle('Page Test: New Title');
        $categoryResourceModel = $this->objectManager->get(CategoryResourceModel::class);
        $categoryResourceModel->save($category);

        $categoryIndexingEntity = $this->getIndexingEntityForCategory($apiKey, $category);
        $this->assertNotNull($categoryIndexingEntity);
        $this->assertFalse(condition: $categoryIndexingEntity->getIsIndexable());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $categoryIndexingEntity->getNextAction());

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testAroundSave_ForExistingCategory_NextActionDeleteChangedToUpdate(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();

        $this->createCategory([
            'is_active' => false,
            'store_id' => (int)$store->getId(),
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_category');
        /** @var Category&CategoryInterface $category */
        $category = $categoryFixture->getCategory();
        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::TARGET_ID => $category->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $category->setIsActive(true);
        $categoryResourceModel = $this->objectManager->get(CategoryResourceModel::class);
        $categoryResourceModel->save($category);

        $cmsIndexingEntity = $this->getIndexingEntityForCategory($apiKey, $category);
        $this->assertNotNull($cmsIndexingEntity);
        $this->assertSame(expected: Actions::UPDATE, actual: $cmsIndexingEntity->getNextAction());

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_categories 1
     */
    public function testAroundSave_MakesIndexableEntityIndexableAgain(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();

        $this->createCategory([
            'is_active' => false,
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_category');
        $category = $categoryFixture->getCategory();

        $categoryIndexingEntity = $this->getIndexingEntityForCategory($apiKey, $category);
        $this->assertNotNull($categoryIndexingEntity);
        $this->assertFalse(condition: $categoryIndexingEntity->getIsIndexable());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $categoryIndexingEntity->getNextAction());

        $category->setIsActive(true);
        $categoryResourceModel = $this->objectManager->get(CategoryResourceModel::class);
        $categoryResourceModel->save($category);

        $categoryIndexingEntity = $this->getIndexingEntityForCategory($apiKey, $category);
        $this->assertNotNull($categoryIndexingEntity);
        $this->assertTrue(condition: $categoryIndexingEntity->getIsIndexable());
        $this->assertSame(expected: Actions::ADD, actual: $categoryIndexingEntity->getNextAction());

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     * @testWith ["name"]
     *           ["url_key"]
     */
    public function testAroundSave_ForExistingCategory_UpdateNextAction_ForAllDescendents_WhenNameOrUrlChanged(
        string $attribute,
    ): void {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();

        $this->createCategory([
            'key' => 'top_cat',
            'name' => 'Top Category',
        ]);
        $topCategoryFixture = $this->categoryFixturePool->get('top_cat');
        $this->createCategory([
            'key' => 'test_category',
            'name' => 'Test Category',
            'parent' => $topCategoryFixture,
        ]);
        $middleCategoryFixture = $this->categoryFixturePool->get('test_category');
        $this->createCategory([
            'key' => 'bottom_category',
            'name' => 'Bottom Category',
            'parent' => $middleCategoryFixture,
        ]);
        $bottomCategoryFixture = $this->categoryFixturePool->get('bottom_category');

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::TARGET_ID => $topCategoryFixture->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::TARGET_ID => $middleCategoryFixture->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::TARGET_ID => $bottomCategoryFixture->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        /** @var Category&CategoryInterface $topCategory */
        $topCategory = $topCategoryFixture->getCategory();
        $topCategory->setData($attribute, sprintf('category_test_new_%s', $attribute));
        $categoryResourceModel = $this->objectManager->get(CategoryResourceModel::class);
        $categoryResourceModel->save($topCategory);

        $topCategoryIndexingEntity = $this->getIndexingEntityForCategory($apiKey, $topCategory);
        $this->assertNotNull($topCategoryIndexingEntity);
        $this->assertTrue(condition: $topCategoryIndexingEntity->getIsIndexable());
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $topCategoryIndexingEntity->getNextAction(),
            message: sprintf(
                'Expected %s, Received %s',
                Actions::UPDATE->value,
                $topCategoryIndexingEntity->getNextAction()->value,
            ),
        );
        $middleCategoryIndexingEntity = $this->getIndexingEntityForCategory(
            $apiKey,
            $middleCategoryFixture->getCategory(),
        );
        $this->assertNotNull($middleCategoryIndexingEntity);
        $this->assertTrue(condition: $middleCategoryIndexingEntity->getIsIndexable());
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $middleCategoryIndexingEntity->getNextAction(),
            message: sprintf(
                'Expected %s, Received %s',
                Actions::UPDATE->value,
                $middleCategoryIndexingEntity->getNextAction()->value,
            ),
        );
        $bottomCategoryIndexingEntity = $this->getIndexingEntityForCategory(
            $apiKey,
            $bottomCategoryFixture->getCategory(),
        );
        $this->assertNotNull($bottomCategoryIndexingEntity);
        $this->assertTrue(condition: $bottomCategoryIndexingEntity->getIsIndexable());
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $bottomCategoryIndexingEntity->getNextAction(),
            message: sprintf(
                'Expected %s, Received %s',
                Actions::UPDATE->value,
                $bottomCategoryIndexingEntity->getNextAction()->value,
            ),
        );

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @return mixed[]|null
     */
    private function getSystemConfigPluginInfo(): ?array
    {
        /** @var PluginList $pluginList */
        $pluginList = $this->objectManager->get(PluginList::class);

        return $pluginList->get(CategoryResourceModel::class, []);
    }

    /**
     * @param string $apiKey
     * @param CategoryInterface $category
     *
     * @return IndexingEntityInterface|null
     * @throws \Exception
     */
    private function getIndexingEntityForCategory(
        string $apiKey,
        CategoryInterface $category,
    ): ?IndexingEntityInterface {
        $categoryIndexingEntities = $this->getCategoryIndexingEntities($apiKey);
        $categoryIndexingEntityArray = array_filter(
            array: $categoryIndexingEntities,
            callback: static fn (IndexingEntityInterface $indexingEntity) => (
                (int)$indexingEntity->getTargetId() === (int)$category->getId()
            )
        );

        return array_shift($categoryIndexingEntityArray);
    }

    /**
     * @return array<IndexingEntityInterface|DataObject>
     * @throws \Exception
     */
    private function getCategoryIndexingEntities(?string $apiKey = null): array
    {
        $collectionFactory = $this->objectManager->get(IndexingEntityCollectionFactory::class);
        /** @var IndexingEntityCollection $collection */
        $collection = $collectionFactory->create();
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
}
