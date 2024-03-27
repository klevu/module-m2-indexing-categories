<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Observer;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity\Collection;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\IndexingEntityRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Klevu\IndexingCategories\Observer\CategoryMoveObserver;
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\CategoryManagementInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\Event\ConfigInterface as EventConfig;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\CategoryFixturePool;

/**
 * @covers \Klevu\IndexingCategories\Observer\CategoryMoveObserver
 * @method ObserverInterface instantiateTestObject(?array $arguments = null)
 * @method ObserverInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class CategoryMoveObserverTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use CategoryTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

    private const OBSERVER_NAME = 'Klevu_IndexingCategories_CategoryMove';
    private const EVENT_NAME = 'category_move';

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

        $this->implementationFqcn = CategoryMoveObserver::class;
        $this->interfaceFqcn = ObserverInterface::class;
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

    public function testObserver_IsConfigured(): void
    {
        $observerConfig = $this->objectManager->create(type: EventConfig::class);
        $observers = $observerConfig->getObservers(eventName: self::EVENT_NAME);

        $this->assertArrayHasKey(key: self::OBSERVER_NAME, array: $observers);
        $this->assertSame(
            expected: ltrim(string: CategoryMoveObserver::class, characters: '\\'),
            actual: $observers[self::OBSERVER_NAME]['instance'],
        );
    }

    /**
     * @magentoAppArea adminhtml
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testMovedCategory_TriggersUpdateOf_NewParent_AndOriginalParent_DeletionAndAddOfChild(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $this->createCategory([
            'key' => 'original_parent_category',
            'title' => 'Original Parent Category',
            'store_id' => $storeFixture->getId(),
        ]);
        $origParentCategoryFixture = $this->categoryFixturePool->get('original_parent_category');

        $this->createCategory([
            'key' => 'child_category',
            'title' => 'Child Category',
            'parent' => $origParentCategoryFixture,
            'store_id' => $storeFixture->getId(),
        ]);
        $childCategoryFixture = $this->categoryFixturePool->get('child_category');

        $this->createCategory([
            'key' => 'new_parent_category',
            'title' => 'New Parent Category',
            'store_id' => $storeFixture->getId(),
        ]);
        $newParentCategoryFixture = $this->categoryFixturePool->get('new_parent_category');

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $origParentCategoryFixture->getId(),
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $childCategoryFixture->getId(),
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $newParentCategoryFixture->getId(),
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $categoryManagement = $this->objectManager->get(CategoryManagementInterface::class);
        $categoryManagement->move(
            categoryId: $childCategoryFixture->getId(),
            parentId: $newParentCategoryFixture->getId(),
        );

        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(IndexingEntity::API_KEY, ['eq' => $apiKey]);
        $collection->addFieldToFilter(IndexingEntity::TARGET_ENTITY_TYPE, ['eq' => 'KLEVU_CATEGORY']);
        /** @var IndexingEntity[] $indexingEntities */
        $indexingEntities = $collection->getItems();

        $this->assertCount(expectedCount: 3, haystack: $indexingEntities);

        /** @var IndexingEntity[] $origParentArray */
        $origParentArray = array_filter(
            array: $indexingEntities,
            callback: static fn (IndexingEntity $indexingEntity): bool => (
                (int)$indexingEntity->getTargetId() === (int)$origParentCategoryFixture->getId()
            ),
        );
        $origParent = array_shift($origParentArray);
        $this->assertInstanceOf(expected: IndexingEntityInterface::class, actual: $origParent);
        $this->assertSame(expected: Actions::UPDATE, actual: $origParent->getNextAction());
        $this->assertTrue(condition: $origParent->getIsIndexable());

        /** @var IndexingEntity[] $newParentArray */
        $newParentArray = array_filter(
            array: $indexingEntities,
            callback: static fn (IndexingEntity $indexingEntity): bool => (
                (int)$indexingEntity->getTargetId() === (int)$newParentCategoryFixture->getId()
            ),
        );
        $newParent = array_shift($newParentArray);
        $this->assertInstanceOf(expected: IndexingEntityInterface::class, actual: $newParent);
        $this->assertSame(expected: Actions::UPDATE, actual: $newParent->getNextAction());
        $this->assertTrue(condition: $newParent->getIsIndexable());

        $childCategory = $childCategoryFixture->getCategory();
        /** @var IndexingEntity[] $childCategoryArray */
        $childCategoryArray = array_filter(
            array: $indexingEntities,
            callback: static fn (IndexingEntity $indexingEntity): bool => (
                (int)$indexingEntity->getTargetId() === (int)$childCategory->getId()
            ),
        );
        $childCategoryDelete = array_shift($childCategoryArray);
        $this->assertInstanceOf(expected: IndexingEntityInterface::class, actual: $childCategoryDelete);
        $this->assertSame(expected: Actions::UPDATE, actual: $childCategoryDelete->getNextAction());
        $this->assertTrue(condition: $childCategoryDelete->getIsIndexable());
    }

    /**
     * @testWith ["category_id"]
     *           ["prev_parent_id"]
     *           ["parent_id"]
     */
    public function testResponderServiceNotCalled_ForInvalidData(string $removeKey): void
    {
        $mockResponderService = $this->getMockBuilder(EntityUpdateResponderServiceInterface::class)
            ->getMock();
        $mockResponderService->expects($this->never())
            ->method('execute');

        $cmsPageDeleteObserver = $this->objectManager->create(CategoryMoveObserver::class, [
            'responderService' => $mockResponderService,
        ]);
        $this->objectManager->addSharedInstance(
            instance: $cmsPageDeleteObserver,
            className: CategoryMoveObserver::class,
        );

        $data = [
            'category' => $this->objectManager->get(CategoryInterface::class),
            'parent' => $this->objectManager->get(CategoryInterface::class),
            'category_id' => 1,
            'prev_parent_id' => 2,
            'parent_id' => 3,
        ];
        unset($data[$removeKey]);

        $this->dispatchEvent(
            event: self::EVENT_NAME,
            data: $data,
        );
    }

    /**
     * @param string $event
     * @param mixed[] $data
     *
     * @return void
     */
    private function dispatchEvent(
        string $event,
        array $data,
    ): void {
        /**
         * $data format
         * [
         *   'category' => CategoryInterface::class,
         *   'parent' => CategoryInterface::class,
         *   'category_id' => int,
         *   'prev_parent_id' => int,
         *   'parent_id' => int,
         * ]
         */
        /** @var EventManager $eventManager */
        $eventManager = $this->objectManager->get(type: EventManager::class);
        $eventManager->dispatch(
            $event,
            $data,
        );
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
        $indexingEntity->setTargetParentId($data[IndexingEntity::TARGET_PARENT_ID] ?? null);
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
