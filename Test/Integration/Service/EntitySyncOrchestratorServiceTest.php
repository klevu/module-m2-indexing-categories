<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Service;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Exception\InvalidEntityIndexerServiceException;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\EntitySyncOrchestratorService;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexerResultInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\EntitySyncOrchestratorServiceInterface;
use Klevu\PhpSDK\Model\Indexing\RecordIterator;
use Klevu\PhpSDKPipelines\Model\ApiPipelineResult;
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\PipelineEntityApiCallTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TddWizard\Fixtures\Catalog\CategoryFixturePool;

class EntitySyncOrchestratorServiceTest extends TestCase
{
    use CategoryTrait;
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use PipelineEntityApiCallTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;

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

        $this->implementationFqcn = EntitySyncOrchestratorService::class;
        $this->interfaceFqcn = EntitySyncOrchestratorServiceInterface::class;
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

    public function testConstruct_ThrowsException_ForInvalidAttributeIndexerService(): void
    {
        $this->expectException(InvalidEntityIndexerServiceException::class);

        $this->instantiateTestObject([
            'entityIndexerServices' => [
                'KLEVU_CATEGORY' => [
                    'add' => new DataObject(),
                ],
            ],
        ]);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_LogsError_ForInvalidAccountCredentials(): void
    {
        $apiKey = 'invalid-js-api-key';
        $authKey = 'invalid-rest-auth-key';

        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );

        $this->createCategory();
        $categoryFixture = $this->categoryFixturePool->get('test_category');

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $categoryFixture->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('warning')
            ->with(
                'Method: {method}, Warning: {message}',
                [
                    'method' => 'Klevu\Indexing\Service\EntitySyncOrchestratorService::getCredentialsArray',
                    'message' => 'No Account found for provided API Key. '
                        . 'Check the JS API Key (incorrect-key) provided.',
                ],
            );

        $service = $this->instantiateTestObject([
            'logger' => $mockLogger,
            'entityIndexerServices' => [],
        ]);
        $service->execute(apiKey: 'incorrect-key');

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_SyncsNewEntity(): void
    {
        $apiKey = 'klevu-123456789';
        $authKey = 'SomeValidRestKey123';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );

        $this->createCategory([
            'key' => 'top_cat',
            'name' => 'Top Category',
        ]);
        $topCategoryFixture = $this->categoryFixturePool->get('top_cat');
        $this->createCategory([
            'key' => 'test_category',
            'name' => 'Test Category',
            'url_key' => 'test-category-url',
            'description' => 'Test Category Description',
            'parent' => $topCategoryFixture,
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_category');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $categoryFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $this->mockBatchServicePutApiCall(isCalled: true, isSuccessful: true);
        $this->mockBatchServiceDeleteApiCall(isCalled: false);

        $service = $this->instantiateTestObject();
        $result = $service->execute(
            entityType: 'KLEVU_CATEGORY',
            apiKey: $apiKey,
            via: 'CLI::klevu:indexing:entity-sync',
        );

        $this->assertCount(expectedCount: 1, haystack: $result);
        $this->assertArrayHasKey(key: $apiKey, array: $result);

        /** @var IndexerResultInterface $integration1 */
        $integration1 = $result[$apiKey];
        $pipelineResults = $integration1->getPipelineResult();
        $this->assertCount(expectedCount: 3, haystack: $pipelineResults);

        $this->assertArrayHasKey(key: 'KLEVU_CATEGORY::delete', array: $pipelineResults);
        $deleteResponses = $pipelineResults['KLEVU_CATEGORY::delete'];
        $this->assertCount(expectedCount: 0, haystack: $deleteResponses);

        $this->assertArrayHasKey(key: 'KLEVU_CATEGORY::update', array: $pipelineResults);
        $updateResponses = $pipelineResults['KLEVU_CATEGORY::update'];
        $this->assertCount(expectedCount: 0, haystack: $updateResponses);

        $this->assertArrayHasKey(key: 'KLEVU_CATEGORY::add', array: $pipelineResults);
        $addResponses = $pipelineResults['KLEVU_CATEGORY::add'];
        $this->assertCount(expectedCount: 1, haystack: $addResponses);

        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($addResponses);

        $this->assertTrue(condition: $pipelineResult->success);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResult->messages);
        $this->assertContains(needle: 'Batch accepted successfully', haystack: $pipelineResult->messages);

        /** @var RecordIterator $payload */
        $payload = $pipelineResult->payload;
        $this->assertCount(expectedCount: 1, haystack: $payload);
        $record = $payload->current();

        $this->assertSame(
            expected: 'categoryid_' . $categoryFixture->getId(),
            actual: $record->getId(),
            message: 'Record ID: ' . $record->getId(),
        );
        $this->assertSame(
            expected: 'KLEVU_CATEGORY',
            actual: $record->getType(),
            message: 'Record Type: ' . $record->getType(),
        );

        $attributes = $record->getAttributes();
        $this->assertArrayHasKey(key: 'name', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['name']);
        $this->assertSame(
            expected: 'Test Category',
            actual: $attributes['name']['default'],
            message: 'Name: ' . $attributes['name']['default'],
        );

        $this->assertArrayHasKey(key: 'description', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['description']);
        $this->assertSame(
            expected: 'Test Category Description',
            actual: $attributes['description']['default'],
            message: 'Description: ' . $attributes['description']['default'],
        );

        $this->assertArrayHasKey(key: 'visibility', array: $attributes);
        $this->assertNotContains(needle: 'catalog', haystack: $attributes['visibility']);
        $this->assertContains(needle: 'search', haystack: $attributes['visibility']);

        $this->assertArrayHasKey(key: 'url', array: $attributes);
        $this->assertStringContainsString(needle: '/test-category-url', haystack: $attributes['url']);

        $this->assertArrayHasKey(key: 'categoryPath', array: $attributes);
        $this->assertStringContainsString(needle: 'Top Category/Test Category', haystack: $attributes['categoryPath']);

        $updatedIndexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $categoryFixture->getCategory(),
            type: 'KLEVU_CATEGORY',
        );
        $this->assertSame(expected: Actions::ADD, actual: $updatedIndexingEntity->getLastAction());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $updatedIndexingEntity->getNextAction());
        $this->assertTrue(condition: $updatedIndexingEntity->getIsIndexable());
        $this->assertNotNull(actual: $updatedIndexingEntity->getLastActionTimestamp());

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_SyncsEntityUpdate(): void
    {
        $apiKey = 'klevu-123456789';
        $authKey = 'SomeValidRestKey123';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );

        $this->createCategory([
            'key' => 'top_cat',
            'name' => 'Top Category',
        ]);
        $topCategoryFixture = $this->categoryFixturePool->get('top_cat');
        $this->createCategory([
            'key' => 'test_category',
            'name' => 'Test Category',
            'url_key' => 'test-category-url',
            'description' => 'Test Category Description',
            'parent' => $topCategoryFixture,
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_category');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $categoryFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
        ]);

        $this->mockBatchServicePutApiCall(isCalled: true, isSuccessful: true);
        $this->mockBatchServiceDeleteApiCall(isCalled: false);

        $service = $this->instantiateTestObject();
        $result = $service->execute(
            entityType: 'KLEVU_CATEGORY',
            apiKey: $apiKey,
            via: 'CLI::klevu:indexing:entity-sync',
        );

        $this->assertCount(expectedCount: 1, haystack: $result);
        $this->assertArrayHasKey(key: $apiKey, array: $result);

        /** @var IndexerResultInterface $integration1 */
        $integration1 = $result[$apiKey];
        $pipelineResults = $integration1->getPipelineResult();
        $this->assertCount(expectedCount: 3, haystack: $pipelineResults);

        $this->assertArrayHasKey(key: 'KLEVU_CATEGORY::delete', array: $pipelineResults);
        $deleteResponses = $pipelineResults['KLEVU_CATEGORY::delete'];
        $this->assertCount(expectedCount: 0, haystack: $deleteResponses);

        $this->assertArrayHasKey(key: 'KLEVU_CATEGORY::add', array: $pipelineResults);
        $addResponses = $pipelineResults['KLEVU_CATEGORY::add'];
        $this->assertCount(expectedCount: 0, haystack: $addResponses);

        $this->assertArrayHasKey(key: 'KLEVU_CATEGORY::update', array: $pipelineResults);
        $updateResponses = $pipelineResults['KLEVU_CATEGORY::update'];
        $this->assertCount(expectedCount: 1, haystack: $updateResponses);

        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($updateResponses);

        $this->assertTrue(condition: $pipelineResult->success);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResult->messages);
        $this->assertContains(needle: 'Batch accepted successfully', haystack: $pipelineResult->messages);

        /** @var RecordIterator $payload */
        $payload = $pipelineResult->payload;
        $this->assertCount(expectedCount: 1, haystack: $payload);
        $record = $payload->current();

        $this->assertSame(
            expected: 'categoryid_' . $categoryFixture->getId(),
            actual: $record->getId(),
            message: 'Record ID: ' . $record->getId(),
        );
        $this->assertSame(
            expected: 'KLEVU_CATEGORY',
            actual: $record->getType(),
            message: 'Record Type: ' . $record->getType(),
        );

        $attributes = $record->getAttributes();
        $this->assertArrayHasKey(key: 'name', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['name']);
        $this->assertSame(
            expected: 'Test Category',
            actual: $attributes['name']['default'],
            message: 'Name: ' . $attributes['name']['default'],
        );

        $this->assertArrayHasKey(key: 'description', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['description']);
        $this->assertSame(
            expected: 'Test Category Description',
            actual: $attributes['description']['default'],
            message: 'Description: ' . $attributes['description']['default'],
        );

        $this->assertArrayHasKey(key: 'visibility', array: $attributes);
        $this->assertNotContains(needle: 'catalog', haystack: $attributes['visibility']);
        $this->assertContains(needle: 'search', haystack: $attributes['visibility']);

        $this->assertArrayHasKey(key: 'url', array: $attributes);
        $this->assertStringContainsString(needle: '/test-category-url', haystack: $attributes['url']);

        $this->assertArrayHasKey(key: 'categoryPath', array: $attributes);
        $this->assertStringContainsString(needle: 'Top Category/Test Category', haystack: $attributes['categoryPath']);

        $updatedIndexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $categoryFixture->getCategory(),
            type: 'KLEVU_CATEGORY',
        );
        $this->assertSame(expected: Actions::UPDATE, actual: $updatedIndexingEntity->getLastAction());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $updatedIndexingEntity->getNextAction());
        $this->assertTrue(condition: $updatedIndexingEntity->getIsIndexable());
        $this->assertNotNull(actual: $updatedIndexingEntity->getLastActionTimestamp());

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_DeletesEntity(): void
    {
        $apiKey = 'klevu-123456789';
        $authKey = 'SomeValidRestKey123';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );

        $this->createCategory([
            'key' => 'top_cat',
            'name' => 'Top Category',
        ]);
        $topCategoryFixture = $this->categoryFixturePool->get('top_cat');
        $this->createCategory([
            'key' => 'test_category',
            'name' => 'Test Category',
            'url_key' => 'test-category-url',
            'description' => 'Test Category Description',
            'parent' => $topCategoryFixture,
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_category');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $categoryFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
        ]);

        $this->mockBatchServicePutApiCall(isCalled: false);
        $this->mockBatchServiceDeleteApiCall(isCalled: true, isSuccessful: true);

        $service = $this->instantiateTestObject();
        $result = $service->execute(
            entityType: 'KLEVU_CATEGORY',
            apiKey: $apiKey,
            via: 'CLI::klevu:indexing:entity-sync',
        );

        $this->assertCount(expectedCount: 1, haystack: $result);
        $this->assertArrayHasKey(key: $apiKey, array: $result);

        /** @var IndexerResultInterface $integration1 */
        $integration1 = $result[$apiKey];
        $pipelineResults = $integration1->getPipelineResult();
        $this->assertCount(expectedCount: 3, haystack: $pipelineResults);

        $this->assertArrayHasKey(key: 'KLEVU_CATEGORY::add', array: $pipelineResults);
        $addResponses = $pipelineResults['KLEVU_CATEGORY::add'];
        $this->assertCount(expectedCount: 0, haystack: $addResponses);

        $this->assertArrayHasKey(key: 'KLEVU_CATEGORY::update', array: $pipelineResults);
        $updateResponses = $pipelineResults['KLEVU_CATEGORY::update'];
        $this->assertCount(expectedCount: 0, haystack: $updateResponses);

        $this->assertArrayHasKey(key: 'KLEVU_CATEGORY::delete', array: $pipelineResults);
        $deleteResponses = $pipelineResults['KLEVU_CATEGORY::delete'];
        $this->assertCount(expectedCount: 1, haystack: $deleteResponses);

        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($deleteResponses);

        $this->assertTrue(condition: $pipelineResult->success);
        $this->assertCount(expectedCount: 1, haystack: $pipelineResult->messages);
        $this->assertContains(needle: 'Batch accepted successfully', haystack: $pipelineResult->messages);

        /** @var RecordIterator $payload */
        $payload = $pipelineResult->payload;
        $this->assertContains(
            needle: 'categoryid_' . $categoryFixture->getId(),
            haystack: $payload,
        );

        $updatedIndexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $categoryFixture->getCategory(),
            type: 'KLEVU_CATEGORY',
        );
        $this->assertSame(expected: Actions::DELETE, actual: $updatedIndexingEntity->getLastAction());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $updatedIndexingEntity->getNextAction());
        $this->assertFalse(condition: $updatedIndexingEntity->getIsIndexable());
        $this->assertNotNull(actual: $updatedIndexingEntity->getLastActionTimestamp());

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }
}