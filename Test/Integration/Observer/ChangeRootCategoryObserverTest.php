<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Observer;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingCategories\Observer\ChangeRootCategoryObserver;
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreGroupFixturesPool;
use Klevu\TestFixtures\Store\StoreGroupTrait;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Framework\Event\ConfigInterface as EventConfig;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\Group;
use Magento\Store\Model\ResourceModel\Group as GroupResourceModel;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\CategoryFixturePool;

/**
 * @covers ChangeRootCategoryObserver
 * @method ObserverInterface instantiateTestObject(?array $arguments = null)
 * @method ObserverInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ChangeRootCategoryObserverTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use CategoryTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use StoreGroupTrait;
    use TestImplementsInterfaceTrait;
    use WebsiteTrait;

    private const OBSERVER_NAME = 'Klevu_IndexingCategories_ChangeRootCategory';
    private const EVENT_NAME = 'store_group_save_commit_after';

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

        $this->implementationFqcn = ChangeRootCategoryObserver::class;
        $this->interfaceFqcn = ObserverInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->websiteFixturesPool = $this->objectManager->get(WebsiteFixturesPool::class);
        $this->storeGroupFixturesPool = $this->objectManager->get(StoreGroupFixturesPool::class);
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
        $this->storeGroupFixturesPool->rollback();
        $this->websiteFixturesPool->rollback();
    }

    public function testObserver_IsConfigured(): void
    {
        $observerConfig = $this->objectManager->create(type: EventConfig::class);
        $observers = $observerConfig->getObservers(eventName: self::EVENT_NAME);

        $this->assertArrayHasKey(key: self::OBSERVER_NAME, array: $observers);
        $this->assertSame(
            expected: ltrim(string: ChangeRootCategoryObserver::class, characters: '\\'),
            actual: $observers[self::OBSERVER_NAME]['instance'],
        );
    }

    public function testSavedStoreGroup_WithSameRootCategory_DoesNotTriggerDiscovery(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'KlevuRestAuthKey123',
        );

        $this->createCategory([
            'key' => 'original_parent_category',
            'title' => 'Original Parent Category',
            'store_id' => $storeFixture->getId(),
        ]);
        $parentCategoryFixture = $this->categoryFixturePool->get('original_parent_category');

        $this->createCategory([
            'key' => 'child_category',
            'title' => 'Child Category',
            'parent' => $parentCategoryFixture,
            'store_id' => $storeFixture->getId(),
        ]);
        $childCategoryFixture = $this->categoryFixturePool->get('child_category');

        $this->cleanIndexingEntities($apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $parentCategoryFixture->getId(),
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $childCategoryFixture->getId(),
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $storeGroupId = $store->getStoreGroupId();
        $storeGroup = $this->objectManager->get(Group::class);
        $storeGroupResourceModel = $this->objectManager->get(GroupResourceModel::class);
        $storeGroupResourceModel->load($storeGroup, $storeGroupId);
        $storeGroup->setName('Test Name');
        $storeGroupResourceModel->save($storeGroup);

        $parentIndexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $parentCategoryFixture->getCategory(),
            type: 'KLEVU_CATEGORY',
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $parentIndexingEntity->getNextAction(),
            message: 'Expected ' . Actions::NO_ACTION->value
                . ', received ' . $parentIndexingEntity->getNextAction()->value,
        );

        $childIndexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $childCategoryFixture->getCategory(),
            type: 'KLEVU_CATEGORY',
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $childIndexingEntity->getNextAction(),
            message: 'Expected ' . Actions::NO_ACTION->value
                . ', received ' . $childIndexingEntity->getNextAction()->value,
        );

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testSavedStoreGroup_WithDifferentRootCategory_TriggersDiscovery(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createWebsite();
        $websiteFixture = $this->websiteFixturesPool->get('test_website');

        $this->createStoreGroup([
            'website_id' => $websiteFixture->getId(),
        ]);
        $storeGroupFixture = $this->storeGroupFixturesPool->get('test_store_group');

        $this->createStore([
            'group_id' => $storeGroupFixture->getId(),
            'website_id' => $websiteFixture->getId(),
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $store = $storeFixture->get();
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'KlevuRestAuthKey123',
        );

        $this->createCategory([
            'key' => 'original_parent_category',
            'title' => 'Original Parent Category',
            'store_id' => $storeFixture->getId(),
            'root_id' => 2,
        ]);
        $origParentCategoryFixture = $this->categoryFixturePool->get('original_parent_category');
        $this->createCategory([
            'key' => 'child_category',
            'title' => 'Child Category',
            'parent' => $origParentCategoryFixture,
            'store_id' => $storeFixture->getId(),
        ]);
        $origChildCategoryFixture = $this->categoryFixturePool->get('child_category');

        $this->createCategory([
            'key' => 'new_root_category',
            'root' => true,
        ]);
        $newRootCategoryFixture = $this->categoryFixturePool->get('new_root_category');
        $this->createCategory([
            'key' => 'new_parent_category',
            'title' => 'New Parent Category',
            'root_id' => $newRootCategoryFixture->getId(),
        ]);
        $newParentCategoryFixture = $this->categoryFixturePool->get('new_parent_category');
        $this->createCategory([
            'key' => 'new_child_category',
            'title' => 'New Child Category',
            'parent' => $newParentCategoryFixture,
        ]);
        $newChildCategoryFixture = $this->categoryFixturePool->get('new_child_category');

        $this->cleanIndexingEntities($apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $origParentCategoryFixture->getId(),
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $origChildCategoryFixture->getId(),
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $storeGroupId = $store->getStoreGroupId();
        $storeGroup = $this->objectManager->get(Group::class);
        $storeGroupResourceModel = $this->objectManager->get(GroupResourceModel::class);
        $storeGroupResourceModel->load($storeGroup, $storeGroupId);
        $storeGroup->setRootCategoryId($newRootCategoryFixture->getId());
        $storeGroupResourceModel->save($storeGroup);

        $parentIndexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $origParentCategoryFixture->getCategory(),
            type: 'KLEVU_CATEGORY',
        );
        $this->assertSame(
            expected: Actions::DELETE,
            actual: $parentIndexingEntity->getNextAction(),
            message: 'Expected ' . Actions::DELETE->value
                . ', received ' . $parentIndexingEntity->getNextAction()->value,
        );

        $childIndexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $origChildCategoryFixture->getCategory(),
            type: 'KLEVU_CATEGORY',
        );
        $this->assertSame(
            expected: Actions::DELETE,
            actual: $childIndexingEntity->getNextAction(),
            message: 'Expected ' . Actions::DELETE->value
                . ', received ' . $childIndexingEntity->getNextAction()->value,
        );

        $newParentIndexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $newParentCategoryFixture->getCategory(),
            type: 'KLEVU_CATEGORY',
        );
        $this->assertSame(
            expected: Actions::ADD,
            actual: $newParentIndexingEntity->getNextAction(),
            message: 'Expected ' . Actions::ADD->value
                . ', received ' . $newParentIndexingEntity->getNextAction()->value,
        );

        $newChildIndexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $newChildCategoryFixture->getCategory(),
            type: 'KLEVU_CATEGORY',
        );
        $this->assertSame(
            expected: Actions::ADD,
            actual: $newChildIndexingEntity->getNextAction(),
            message: 'Expected ' . Actions::ADD->value
                . ', received ' . $newChildIndexingEntity->getNextAction()->value,
        );

        // revert to original store category before rolling back
        $storeGroupResourceModel = $this->objectManager->get(GroupResourceModel::class);
        $storeGroupResourceModel->load($storeGroup, $storeGroupId);
        $storeGroup->setRootCategoryId(2);
        $storeGroupResourceModel->save($storeGroup);

        $this->cleanIndexingEntities($apiKey);
    }
}
