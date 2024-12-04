<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Service\Provider\Sync;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\Provider\Sync\EntityIndexingRecordProvider;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Model\EntityIndexingRecordInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Provider\Sync\EntityIndexingRecordProviderInterface;
use Klevu\IndexingCategories\Service\Provider\Sync\EntityIndexingRecordProvider\Add as AddEntityIndexingRecordProviderVirtualType; //phpcs:ignore Generic.Files.LineLength.TooLong
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\CategoryFixturePool;

/**
 * @covers EntityIndexingRecordProvider::class
 * @method EntityIndexingRecordProviderInterface instantiateTestObject(?array $arguments = null)
 * @method EntityIndexingRecordProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityIndexingRecordProviderTest extends TestCase
{
    use CategoryTrait;
    use IndexingEntitiesTrait;
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

        $this->implementationFqcn = AddEntityIndexingRecordProviderVirtualType::class; //@phpstan-ignore-line
        $this->interfaceFqcn = EntityIndexingRecordProviderInterface::class;
        $this->implementationForVirtualType = EntityIndexingRecordProvider::class;
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
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsEntitiesToAdd_ForCategory_InOneStore(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());

        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-test-auth-key',
        );

        $this->createCategory();
        $categoryFixture = $this->categoryFixturePool->get('test_category');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $indexingEntity = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $categoryFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $provider = $this->instantiateTestObject();
        $generator = $provider->get(apiKey: $apiKey);

        /** @var EntityIndexingRecordInterface[] $result */
        $result = [];
        foreach ($generator as $indexingRecords) {
            $result[] = $indexingRecords;
        }
        $this->assertCount(expectedCount: 1, haystack: $result);
        $this->assertCount(expectedCount: 1, haystack: $result[0]);
        /** @var EntityIndexingRecordInterface $indexingRecord */
        $indexingRecord = $result[0][0] ?? null;

        $this->assertSame(
            expected: $indexingEntity->getId(),
            actual: $indexingRecord->getRecordId(),
        );
        $this->assertSame(
            expected: (int)$categoryFixture->getId(),
            actual: (int)$indexingRecord->getEntity()->getId(),
        );
        $this->assertNull(actual: $indexingRecord->getParent());

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }
}
