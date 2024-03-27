<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Service\Provider;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\IndexingCategories\Service\Provider\CategoryPathProvider;
use Klevu\IndexingCategories\Service\Provider\CategoryPathProviderInterface;
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreGroupFixturesPool;
use Klevu\TestFixtures\Store\StoreGroupTrait;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\CategoryFixturePool;

/**
 * @covers CategoryPathProvider
 * @method CategoryPathProviderInterface instantiateTestObject(?array $arguments = null)
 * @method CategoryPathProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class CategoryPathProviderTest extends TestCase
{
    use CategoryTrait;
    use ObjectInstantiationTrait;
    use StoreGroupTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var ScopeProviderInterface|null
     */
    private ?ScopeProviderInterface $scopeProvider = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = CategoryPathProvider::class;
        $this->interfaceFqcn = CategoryPathProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);

        $this->categoryFixturePool = $this->objectManager->get(CategoryFixturePool::class);
        $this->storeGroupFixturesPool = $this->objectManager->get(StoreGroupFixturesPool::class);
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->storeFixturesPool->rollback();
        $this->storeGroupFixturesPool->rollback();
        $this->categoryFixturePool->rollback();
    }

    public function testGetForCategory_ReturnsString(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

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
        $categoryFixture = $this->categoryFixturePool->get('test_category');
        $this->createCategory([
            'key' => 'bottom_category',
            'name' => 'Bottom Category',
            'parent' => $categoryFixture,
        ]);
        $bottomCategoryFixture = $this->categoryFixturePool->get('bottom_category');

        $provider = $this->instantiateTestObject();
        $result = $provider->getForCategory(
            category: $bottomCategoryFixture->getCategory(),
        );

        $this->assertSame(
            expected: 'Top Category/Test Category/Bottom Category',
            actual: $result,
        );
    }

    public function testGetForCategory_WithExcludedIds_ReturnsString(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

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
        $categoryFixture = $this->categoryFixturePool->get('test_category');
        $this->createCategory([
            'key' => 'bottom_category',
            'name' => 'Bottom Category',
            'parent' => $categoryFixture,
        ]);
        $bottomCategoryFixture = $this->categoryFixturePool->get('bottom_category');

        $provider = $this->instantiateTestObject();
        $result = $provider->getForCategory(
            category: $bottomCategoryFixture->getCategory(),
            excludeCategoryIds: [(int)$topCategoryFixture->getId()],
        );

        $this->assertSame(
            expected: 'Test Category/Bottom Category',
            actual: $result,
        );
    }

    public function testGetForCategoryId_ReturnsString(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

        $this->createCategory([
            'key' => 'top_cat',
            'name' => 'Top Category',
        ]);
        $topCategoryFixture = $this->categoryFixturePool->get('top_cat');
        $this->createCategory([
            'key' => 'test_category',
            'name' => 'Test Category',
            'is_active' => false,
            'parent' => $topCategoryFixture,
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_category');
        $this->createCategory([
            'key' => 'bottom_category',
            'name' => 'Bottom Category',
            'parent' => $categoryFixture,
        ]);
        $bottomCategoryFixture = $this->categoryFixturePool->get('bottom_category');

        $provider = $this->instantiateTestObject();
        $result = $provider->getForCategoryId(
            categoryId: $bottomCategoryFixture->getId(),
        );

        $this->assertSame(
            expected: 'Top Category/Test Category/Bottom Category',
            actual: $result,
        );
    }

    public function testGetForCategoryID_ForNewRootCategory(): void
    {
        $this->createCategory([
            'root' => true,
            'name' => 'New Root Category',
            'key' => 'test_root_category',
        ]);
        $rootCatFixture = $this->categoryFixturePool->get('test_root_category');

        $this->createCategory([
            'root_id' => $rootCatFixture->getId(),
            'name' => 'Top Category',
            'key' => 'test_top_category',
        ]);
        $topCatFixture = $this->categoryFixturePool->get('test_top_category');

        $this->createCategory([
            'parent' => $topCatFixture,
            'name' => 'Middle Category',
            'key' => 'test_top_category',
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_top_category');

        $this->createCategory([
            'key' => 'test_bottom_category',
            'name' => 'Bottom Category',
            'parent' => $categoryFixture,
        ]);
        $bottomCategoryFixture = $this->categoryFixturePool->get('test_bottom_category');

        $this->createStoreGroup([
            'root_category_id' => (int)$rootCatFixture->getId(),
        ]);
        $storeGroupFixture = $this->storeGroupFixturesPool->get('test_store_group');
        $this->createStore([
            'group_id' => (int)$storeGroupFixture->getId(),
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $this->scopeProvider->setCurrentScope($storeFixture->get());

        /**
         * Empty array passed to instantiateTestObject to trigger create rather than get,
         * is more performant than magentoAppIsolation enabled
         */
        $provider = $this->instantiateTestObject([]);
        $result = $provider->getForCategoryId(
            categoryId: $bottomCategoryFixture->getId(),
        );

        $this->assertSame(
            expected: 'Top Category/Middle Category/Bottom Category',
            actual: $result,
        );
    }
}
