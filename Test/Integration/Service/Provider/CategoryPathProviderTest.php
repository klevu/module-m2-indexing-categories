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
use Magento\Catalog\Api\CategoryRepositoryInterface;
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

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetForCategory_ReturnsString(): void
    {
        $this->createStore();
        $storeFixture1 = $this->storeFixturesPool->get('test_store');

        $this->createStore([
            'key' => 'test_store_2',
            'code' => 'klevu_test_store_2',
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');

        $this->createCategory([
            'key' => 'top_cat',
            'name' => 'Top Category',
            'is_active' => false,
            'stores' => [
                $storeFixture1->getId() => [
                    'name' => 'First Store Top Category',
                    'is_active' => true,
                    'description' => 'First Store Top Category Description',
                    'url_key' => 'first-top-category-url',
                ],
                $storeFixture2->getId() => [
                    'name' => 'Second Store Top Category',
                    'is_active' => true,
                    'description' => 'Second Store Top Category Description',
                    'url_key' => 'second-top-category-url',
                ],
            ],
        ]);
        $topCategoryFixture = $this->categoryFixturePool->get('top_cat');
        $this->createCategory([
            'key' => 'test_category',
            'name' => 'Test Category',
            'parent' => $topCategoryFixture,
            'is_active' => false,
            'stores' => [
                $storeFixture1->getId() => [
                    'name' => 'First Store Test Category',
                    'is_active' => true,
                    'description' => 'First Store Category Description',
                    'url_key' => 'first-category-url',
                ],
                $storeFixture2->getId() => [
                    'name' => 'Second Store Test Category',
                    'is_active' => true,
                    'description' => 'Second Store Category Description',
                    'url_key' => 'second-category-url',
                ],
            ],
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_category');
        $this->createCategory([
            'key' => 'bottom_category',
            'name' => 'Bottom Category',
            'is_active' => false,
            'parent' => $categoryFixture,
            'stores' => [
                $storeFixture1->getId() => [
                    'name' => 'First Store Bottom Category',
                    'is_active' => true,
                    'description' => 'First Store Bottom Category Description',
                    'url_key' => 'first-bottom-category-url',
                ],
                $storeFixture2->getId() => [
                    'name' => 'Second Store Bottom Category',
                    'is_active' => true,
                    'description' => 'Second Store Bottom Category Description',
                    'url_key' => 'second-bottom-category-url',
                ],
            ],
        ]);
        $bottomCategoryFixture = $this->categoryFixturePool->get('bottom_category');

        $categoryRepository = $this->objectManager->get(CategoryRepositoryInterface::class);

        $provider = $this->instantiateTestObject();

        $category1 = $categoryRepository->get(
            categoryId:(int)$bottomCategoryFixture->getId(),
            storeId: (int)$storeFixture1->getId(),
        );
        $resultStore1 = $provider->getForCategory(
            category: $category1,
            storeId: (int)$storeFixture1->getId(),
        );
        $this->assertSame(
            expected: 'First Store Top Category;First Store Test Category;First Store Bottom Category',
            actual: $resultStore1,
        );

        $category2 = $categoryRepository->get(
            categoryId:(int)$bottomCategoryFixture->getId(),
            storeId: (int)$storeFixture2->getId(),
        );
        $resultStore2 = $provider->getForCategory(
            category: $category2,
            storeId: (int)$storeFixture2->getId(),
        );
        $this->assertSame(
            expected: 'Second Store Top Category;Second Store Test Category;Second Store Bottom Category',
            actual: $resultStore2,
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetForCategory_WithExcludedIds_ReturnsString(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

        $this->createCategory([
            'key' => 'top_cat',
            'name' => 'Top Category',
            'is_active' => false,
            'stores' => [
                $storeFixture->getId() => [
                    'name' => 'Other Top Category',
                    'is_active' => true,
                    'description' => 'Other Top Category Description',
                    'url_key' => 'other-top-category-url',
                ],
            ],
        ]);
        $topCategoryFixture = $this->categoryFixturePool->get('top_cat');
        $this->createCategory([
            'key' => 'test_category',
            'name' => 'Test Category',
            'parent' => $topCategoryFixture,
            'is_active' => false,
            'stores' => [
                $storeFixture->getId() => [
                    'name' => 'Other Test Category',
                    'is_active' => true,
                    'description' => 'Other Category Description',
                    'url_key' => 'other-category-url',
                ],
            ],
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_category');
        $this->createCategory([
            'key' => 'bottom_category',
            'name' => 'Bottom Category',
            'is_active' => false,
            'parent' => $categoryFixture,
            'stores' => [
                $storeFixture->getId() => [
                    'name' => 'Other Bottom Category',
                    'is_active' => true,
                    'description' => 'Other Bottom Category Description',
                    'url_key' => 'other-bottom-category-url',
                ],
            ],
        ]);
        $bottomCategoryFixture = $this->categoryFixturePool->get('bottom_category');

        $categoryRepository = $this->objectManager->get(CategoryRepositoryInterface::class);
        $category = $categoryRepository->get(
            categoryId:(int)$bottomCategoryFixture->getId(),
            storeId: (int)$storeFixture->getId(),
        );

        $provider = $this->instantiateTestObject();
        $result = $provider->getForCategory(
            category: $category,
            excludeCategoryIds: [(int)$topCategoryFixture->getId()],
        );

        $this->assertSame(
            expected: 'Other Test Category;Other Bottom Category',
            actual: $result,
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetForCategoryId_ReturnsString(): void
    {
        $this->createStore();
        $storeFixture1 = $this->storeFixturesPool->get('test_store');

        $this->createStore([
            'key' => 'test_store_2',
            'code' => 'klevu_test_store_2',
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');

        $this->createCategory([
            'key' => 'top_cat',
            'name' => 'Top Category',
            'is_active' => false,
            'stores' => [
                $storeFixture1->getId() => [
                    'name' => 'First Store Top Category',
                    'is_active' => true,
                    'description' => 'First Store Top Category Description',
                    'url_key' => 'first-top-category-url',
                ],
                $storeFixture2->getId() => [
                    'name' => 'Second Store Top Category',
                    'is_active' => true,
                    'description' => 'Second Store Top Category Description',
                    'url_key' => 'second-top-category-url',
                ],
            ],
        ]);
        $topCategoryFixture = $this->categoryFixturePool->get('top_cat');
        $this->createCategory([
            'key' => 'test_category',
            'name' => 'Test Category',
            'parent' => $topCategoryFixture,
            'is_active' => false,
            'stores' => [
                $storeFixture1->getId() => [
                    'name' => 'First Store Test Category',
                    'is_active' => true,
                    'description' => 'First Store Category Description',
                    'url_key' => 'first-category-url',
                ],
                $storeFixture2->getId() => [
                    'name' => 'Second Store Test Category',
                    'is_active' => true,
                    'description' => 'Second Store Category Description',
                    'url_key' => 'second-category-url',
                ],
            ],
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_category');
        $this->createCategory([
            'key' => 'bottom_category',
            'name' => 'Bottom Category',
            'is_active' => false,
            'parent' => $categoryFixture,
            'stores' => [
                $storeFixture1->getId() => [
                    'name' => 'First Store Bottom Category',
                    'is_active' => true,
                    'description' => 'First Store Bottom Category Description',
                    'url_key' => 'first-bottom-category-url',
                ],
                $storeFixture2->getId() => [
                    'name' => 'Second Store Bottom Category',
                    'is_active' => true,
                    'description' => 'Second Store Bottom Category Description',
                    'url_key' => 'second-bottom-category-url',
                ],
            ],
        ]);
        $bottomCategoryFixture = $this->categoryFixturePool->get('bottom_category');

        $provider = $this->instantiateTestObject();

        $resultStore1 = $provider->getForCategoryId(
            categoryId: (int)$bottomCategoryFixture->getId(),
            storeId: (int)$storeFixture1->getId(),
        );
        $this->assertSame(
            expected: 'First Store Top Category;First Store Test Category;First Store Bottom Category',
            actual: $resultStore1,
        );

        $resultStore2 = $provider->getForCategoryId(
            categoryId: (int)$bottomCategoryFixture->getId(),
            storeId: (int)$storeFixture2->getId(),
        );
        $this->assertSame(
            expected: 'Second Store Top Category;Second Store Test Category;Second Store Bottom Category',
            actual: $resultStore2,
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
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
            categoryId: (int)$bottomCategoryFixture->getId(),
            storeId: (int)$storeFixture->getId(),
        );

        $this->assertSame(
            expected: 'Top Category;Middle Category;Bottom Category',
            actual: $result,
        );
    }
}
