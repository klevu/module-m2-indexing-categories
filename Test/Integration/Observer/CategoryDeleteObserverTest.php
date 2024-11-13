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
use Klevu\IndexingCategories\Observer\CategoryDeleteObserver;
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResourceModel;
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
 * @covers \Klevu\IndexingCategories\Observer\CategoryDeleteObserver
 * @method ObserverInterface instantiateTestObject(?array $arguments = null)
 * @method ObserverInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class CategoryDeleteObserverTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use CategoryTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

    private const OBSERVER_NAME = 'Klevu_IndexingCategories_CategoryDelete';
    private const EVENT_NAME = 'magento_catalog_api_data_categoryinterface_delete_after';

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

        $this->implementationFqcn = CategoryDeleteObserver::class;
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
            expected: ltrim(string: CategoryDeleteObserver::class, characters: '\\'),
            actual: $observers[self::OBSERVER_NAME]['instance'],
        );
    }

    /**
     * @magentoAppArea adminhtml
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testDeletedCategory_newIndexingEntityIsSetToNotIndexable(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $this->createCategory([
            'store_id' => $storeFixture->getId(),
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_category');

        $category = $categoryFixture->getCategory();
        $categoryResourceModel = $this->objectManager->get(CategoryResourceModel::class);
        $categoryResourceModel->delete($category);

        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(IndexingEntity::TARGET_ID, ['eq' => $category->getId()]);
        $collection->addFieldToFilter(IndexingEntity::API_KEY, ['eq' => $apiKey]);
        $collection->addFieldToFilter(IndexingEntity::TARGET_ENTITY_TYPE, ['eq' => 'KLEVU_CATEGORY']);
        $collection->addFieldToFilter(IndexingEntity::TARGET_PARENT_ID, ['null' => true]);
        $indexingEntities = $collection->getItems();

        /** @var IndexingEntityInterface $indexingEntity */
        $indexingEntity = array_shift($indexingEntities);
        $this->assertInstanceOf(expected: IndexingEntityInterface::class, actual: $indexingEntity);
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $indexingEntity->getNextAction(),
            message:'Next Action: No Action',
        );
        $this->assertFalse(condition: $indexingEntity->getIsIndexable());
        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @magentoAppArea adminhtml
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testDeletedCategory_IndexingEntityIsSetToDelete(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $this->createCategory([
            'store_id' => $storeFixture->getId(),
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_category');

        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $categoryFixture->getId(),
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $category = $categoryFixture->getCategory();
        $categoryResourceModel = $this->objectManager->get(CategoryResourceModel::class);
        $categoryResourceModel->delete($category);

        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(IndexingEntity::TARGET_ID, ['eq' => $category->getId()]);
        $collection->addFieldToFilter(IndexingEntity::API_KEY, ['eq' => $apiKey]);
        $collection->addFieldToFilter(IndexingEntity::TARGET_ENTITY_TYPE, ['eq' => 'KLEVU_CATEGORY']);
        $collection->addFieldToFilter(IndexingEntity::TARGET_PARENT_ID, ['null' => true]);
        $indexingEntities = $collection->getItems();

        /** @var IndexingEntityInterface $indexingEntity */
        $indexingEntity = array_shift($indexingEntities);
        $this->assertInstanceOf(expected: IndexingEntityInterface::class, actual: $indexingEntity);
        $this->assertSame(
            expected: Actions::DELETE,
            actual: $indexingEntity->getNextAction(),
            message:'Next Action: Delete',
        );
        $this->assertTrue(condition: $indexingEntity->getIsIndexable());
        $this->cleanIndexingEntities($apiKey);
    }

    public function testResponderServiceNotCalled_ForNonCategoryInterface(): void
    {
        $mockResponderService = $this->getMockBuilder(EntityUpdateResponderServiceInterface::class)
            ->getMock();
        $mockResponderService->expects($this->never())
            ->method('execute');

        $cmsPageDeleteObserver = $this->objectManager->create(CategoryDeleteObserver::class, [
            'responderService' => $mockResponderService,
        ]);
        $this->objectManager->addSharedInstance(
            instance: $cmsPageDeleteObserver,
            className: CategoryDeleteObserver::class,
        );

        $product = $this->objectManager->get(ProductInterface::class);
        $this->dispatchEvent(
            event: self::EVENT_NAME,
            entity: $product,
        );
    }

    /**
     * @param string $event
     * @param mixed $entity
     *
     * @return void
     */
    private function dispatchEvent(
        string $event,
        mixed $entity,
    ): void {
        /** @var EventManager $eventManager */
        $eventManager = $this->objectManager->get(type: EventManager::class);
        $eventManager->dispatch(
            $event,
            [
                'entity' => $entity,
            ],
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
        $indexingEntity->setTargetEntitySubtype($data[IndexingEntity::TARGET_ENTITY_SUBTYPE] ?? 'category');
        $indexingEntity->setApiKey($data[IndexingEntity::API_KEY] ?? 'klevu-js-api-key');
        $indexingEntity->setNextAction($data[IndexingEntity::NEXT_ACTION] ?? Actions::NO_ACTION);
        $indexingEntity->setLastAction($data[IndexingEntity::LAST_ACTION] ?? Actions::NO_ACTION);
        $indexingEntity->setLastActionTimestamp($data[IndexingEntity::LAST_ACTION_TIMESTAMP] ?? null);
        $indexingEntity->setLockTimestamp($data[IndexingEntity::LOCK_TIMESTAMP] ?? null);
        $indexingEntity->setIsIndexable($data[IndexingEntity::IS_INDEXABLE] ?? true);

        return $repository->save($indexingEntity);
    }
}
