<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Service\Provider;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\IndexingApi\Service\Provider\EntityProviderInterface;
use Klevu\IndexingCategories\Constants;
use Klevu\IndexingCategories\Service\Provider\CategoryEntityProvider;
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\GroupRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\CategoryFixturePool;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers CategoryEntityProvider
 * @method EntityProviderInterface instantiateTestObject(?array $arguments = null)
 * @method EntityProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class CategoryEntityProviderTest extends TestCase
{
    use CategoryTrait;
    use ObjectInstantiationTrait;
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

        $this->implementationFqcn = CategoryEntityProvider::class;
        $this->interfaceFqcn = EntityProviderInterface::class;
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
     * @magentoDataFixtureBeforeTransaction Magento/Catalog/_files/enable_reindex_schedule.php
     */
    public function testGet_ReturnsCategoryData_AtStoreScope(): void
    {
        $this->createWebsite([
            'key' => 'test_website',
        ]);
        $websiteFixture = $this->websiteFixturesPool->get('test_website');

        $this->createStore([
            'website_id' => $websiteFixture->getId(),
            'key' => 'test_store',
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

        $currentCategoryCount = $this->getCurrentCategoryCount($storeFixture->get());

        $this->createCategory([
            'key' => 'top_cat',
            'name' => 'Top Category',
            'store_id' => $storeFixture->getId(),
        ]);
        $topCategoryFixture = $this->categoryFixturePool->get('top_cat');
        $this->createCategory([
            'parent' => $topCategoryFixture,
            'name' => 'Child Category',
            'key' => 'child_cat',
            'store_id' => $storeFixture->getId(),
        ]);
        $childCategoryFixture = $this->categoryFixturePool->get('child_cat');

        $provider = $this->instantiateTestObject();
        $searchResults = $provider->get(store: $storeFixture->get());

        $items = [];
        foreach ($searchResults as $searchResult) {
            $items[] = $searchResult;
        }

        $this->assertCount(expectedCount: 2 + $currentCategoryCount, haystack: $items);
        $categoryIds = array_map(
            callback: static function (CategoryInterface $item): int {
                return (int)$item->getId();
            },
            array: $items,
        );
        $this->assertContains(needle: (int)$topCategoryFixture->getId(), haystack: $categoryIds);
        $this->assertContains(needle: (int)$childCategoryFixture->getId(), haystack: $categoryIds);

        $category1Array = array_filter(
            array: $items,
            callback: static function (CategoryInterface $category) use ($topCategoryFixture): bool {
                return (int)$category->getId() === (int)$topCategoryFixture->getId();
            },
        );
        $category1 = array_shift($category1Array);
        $this->assertSame(
            expected: $topCategoryFixture->getId(),
            actual: (int)$category1->getId(),
        );
        $this->assertSame(
            expected: 'Top Category',
            actual: $category1->getName(),
        );

        $category2Array = array_filter(
            array: $items,
            callback: static function (CategoryInterface $category) use ($childCategoryFixture): bool {
                return (int)$category->getId() === (int)$childCategoryFixture->getId();
            },
        );
        $category2 = array_shift($category2Array);
        $this->assertSame(
            expected: $childCategoryFixture->getId(),
            actual: (int)$category2->getId(),
        );
        $this->assertSame(
            expected: 'Child Category',
            actual: $category2->getName(),
        );
    }

    /**
     * @magentoDataFixtureBeforeTransaction Magento/Catalog/_files/enable_reindex_schedule.php
     */
    public function testGet_ReturnsCategoryData_AtGlobalScope(): void
    {
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScopeByCode(scopeCode: 'default', scopeType: ScopeInterface::SCOPE_STORES);

        $currentCategoryCount = $this->getCurrentCategoryCount();

        $this->createCategory([
            'key' => 'top_cat',
            'name' => 'Top Category 2',
        ]);
        $topCategoryFixture = $this->categoryFixturePool->get('top_cat');
        $this->createCategory([
            'parent' => $topCategoryFixture,
            'name' => 'Child Category 2',
            'key' => 'child_cat',
        ]);
        $childCategoryFixture = $this->categoryFixturePool->get('child_cat');

        $provider = $this->instantiateTestObject();
        $searchResults = $provider->get();

        $items = [];
        foreach ($searchResults as $searchResult) {
            $items[] = $searchResult;
        }

        $this->assertCount(expectedCount: 2 + $currentCategoryCount, haystack: $items);
        $categoryIds = array_map(
            callback: static function (CategoryInterface $item): int {
                return (int)$item->getId();
            },
            array: $items,
        );
        $this->assertContains(needle: (int)$topCategoryFixture->getId(), haystack: $categoryIds);
        $this->assertContains(needle: (int)$childCategoryFixture->getId(), haystack: $categoryIds);

        $category1Array = array_filter(
            array: $items,
            callback: static function (CategoryInterface $category) use ($topCategoryFixture): bool {
                return (int)$category->getId() === (int)$topCategoryFixture->getId();
            },
        );
        $category1 = array_shift($category1Array);
        $this->assertSame(
            expected: $topCategoryFixture->getId(),
            actual: (int)$category1->getId(),
        );
        $this->assertSame(
            expected: 'Top Category 2',
            actual: $category1->getName(),
        );

        $category2Array = array_filter(
            array: $items,
            callback: static function (CategoryInterface $category) use ($childCategoryFixture): bool {
                return (int)$category->getId() === (int)$childCategoryFixture->getId();
            },
        );
        $category2 = array_shift($category2Array);
        $this->assertSame(
            expected: $childCategoryFixture->getId(),
            actual: (int)$category2->getId(),
        );
        $this->assertSame(
            expected: 'Child Category 2',
            actual: $category2->getName(),
        );
    }

    /**
     * @magentoDataFixtureBeforeTransaction Magento/Catalog/_files/enable_reindex_schedule.php
     */
    public function testGet_ReturnsCategoryData_ForFilteredEntities(): void
    {
        $this->createWebsite([
            'key' => 'test_website',
        ]);
        $websiteFixture = $this->websiteFixturesPool->get('test_website');

        $this->createStore([
            'website_id' => $websiteFixture->getId(),
            'key' => 'test_store',
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

        $this->createCategory([
            'key' => 'top_cat',
            'name' => 'Top Category',
        ]);
        $topCategoryFixture = $this->categoryFixturePool->get('top_cat');
        $this->createCategory([
            'parent' => $topCategoryFixture,
            'name' => 'Child Category',
            'key' => 'child_cat',
        ]);
        $childCategoryFixture = $this->categoryFixturePool->get('child_cat');

        $provider = $this->instantiateTestObject();
        $searchResults = $provider->get(
            store: $storeFixture->get(),
            entityIds: [(int)$childCategoryFixture->getId()],
        );

        $items = [];
        foreach ($searchResults as $searchResult) {
            $items[] = $searchResult;
        }

        $this->assertCount(expectedCount: 1, haystack: $items);
        $categoryIds = array_map(
            callback: static function (CategoryInterface $item): int {
                return (int)$item->getId();
            },
            array: $items,
        );
        $this->assertNotContains(needle: (int)$topCategoryFixture->getId(), haystack: $categoryIds);
        $this->assertContains(needle: (int)$childCategoryFixture->getId(), haystack: $categoryIds);

        $category2Array = array_filter(
            array: $items,
            callback: static function (CategoryInterface $category) use ($childCategoryFixture): bool {
                return (int)$category->getId() === (int)$childCategoryFixture->getId();
            },
        );
        $category2 = array_shift($category2Array);
        $this->assertSame(
            expected: $childCategoryFixture->getId(),
            actual: (int)$category2->getId(),
        );
        $this->assertSame(
            expected: 'Child Category',
            actual: $category2->getName(),
        );
    }

    /**
     * @magentoDataFixtureBeforeTransaction Magento/Catalog/_files/enable_reindex_schedule.php
     */
    public function testGet_ReturnsNoData_WhenSyncDisabled_AtStoreScope(): void
    {
        $this->createWebsite([
            'key' => 'test_website',
        ]);
        $websiteFixture = $this->websiteFixturesPool->get('test_website');

        $this->createStore([
            'website_id' => $websiteFixture->getId(),
            'key' => 'test_store',
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

        ConfigFixture::setForStore(
            path: Constants::XML_PATH_CATEGORY_SYNC_ENABLED,
            value: 0,
            storeCode: $storeFixture->getCode(),
        );

        $this->createCategory([
            'key' => 'top_cat',
            'name' => 'Top Category',
            'store_id' => $storeFixture->getId(),
        ]);
        $topCategoryFixture = $this->categoryFixturePool->get('top_cat');
        $this->createCategory([
            'parent' => $topCategoryFixture,
            'name' => 'Child Category',
            'key' => 'child_cat',
            'store_id' => $storeFixture->getId(),
        ]);

        $provider = $this->instantiateTestObject();
        $searchResults = $provider->get(store: $storeFixture->get());

        $items = [];
        foreach ($searchResults as $searchResult) {
            $items[] = $searchResult;
        }
        $this->assertCount(expectedCount: 0, haystack: $items);
    }

    /**
     * @param StoreInterface|null $store
     *
     * @return int
     * @throws LocalizedException
     */
    private function getCurrentCategoryCount(?StoreInterface $store = null): int
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

        return $categoryCollection->count();
    }
}
