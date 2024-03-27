<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Service\Determiner;

use Klevu\IndexingApi\Service\Determiner\IsIndexableDeterminerInterface;
use Klevu\IndexingCategories\Service\Determiner\DisabledCategoriesIsIndexableDeterminer;
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\CategoryFixturePool;

/**
 * @covers \Klevu\IndexingCategories\Service\Determiner\DisabledCategoriesIsIndexableDeterminer::class
 * @method IsIndexableDeterminerInterface instantiateTestObject(?array $arguments = null)
 * @method IsIndexableDeterminerInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class DisabledCategoriesIsIndexableDeterminerTest extends TestCase
{
    use CategoryTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-lines

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = DisabledCategoriesIsIndexableDeterminer::class;
        $this->interfaceFqcn = IsIndexableDeterminerInterface::class;
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
     * @magentoDataFixtureBeforeTransaction Magento/Catalog/_files/enable_reindex_schedule.php
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_categories 0
     */
    public function testExecute_ReturnsTrue_WhenConfigDisabled(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $this->createCategory([
            'is_active' => true,
            'store_id' => $storeFixture->getId(),
            'key' => 'top_cat',
        ]);
        $topLevelCategory = $this->categoryFixturePool->get('top_cat');
        $this->createCategory([
            'parent' => $topLevelCategory,
            'is_active' => false,
            'store_id' => $storeFixture->getId(),
            'key' => 'child_cat',
        ]);
        $childCategory = $this->categoryFixturePool->get('child_cat');

        $determiner = $this->instantiateTestObject();
        $this->assertTrue(
            condition: $determiner->execute(
                entity: $childCategory->getCategory(),
                store: $storeFixture->get(),
            ),
            message: 'is indexable',
        );
    }

    /**
     * @magentoDataFixtureBeforeTransaction Magento/Catalog/_files/enable_reindex_schedule.php
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_categories 1
     */
    public function testExecute_ReturnsTrue_WhenConfigEnabled_EntityEnabled(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $this->createCategory([
            'is_active' => true,
            'store_id' => $storeFixture->getId(),
            'key' => 'top_cat',
        ]);
        $topLevelCategory = $this->categoryFixturePool->get('top_cat');
        $this->createCategory([
            'parent' => $topLevelCategory,
            'is_active' => true,
            'store_id' => $storeFixture->getId(),
            'key' => 'child_cat',
        ]);
        $childCategory = $this->categoryFixturePool->get('child_cat');

        $determiner = $this->instantiateTestObject();
        $this->assertTrue(
            condition: $determiner->execute(
                entity: $childCategory->getCategory(),
                store: $storeFixture->get(),
            ),
            message: 'is indexable',
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDataFixtureBeforeTransaction Magento/Catalog/_files/enable_reindex_schedule.php
     * @magentoConfigFixture default/klevu/indexing/exclude_disabled_categories 1
     */
    public function testExecute_ReturnsFalse_WhenConfigEnabled_EntityDisabled(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $this->createCategory([
            'is_active' => true,
            'store_id' => $storeFixture->getId(),
            'key' => 'top_cat',
        ]);
        $topLevelCategory = $this->categoryFixturePool->get('top_cat');
        $this->createCategory([
            'parent' => $topLevelCategory,
            'is_active' => false,
            'store_id' => $storeFixture->getId(),
            'key' => 'child_cat',
        ]);
        $childCategory = $this->categoryFixturePool->get('child_cat');
        $determiner = $this->instantiateTestObject();
        $this->assertFalse(
            condition: $determiner->execute(
                entity: $childCategory->getCategory(),
                store: $storeFixture->get(),
            ),
            message: 'is indexable',
        );
    }

    public function testExecute_ThrowsInvalidArgumentException(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');

        $invalidEntity = $this->objectManager->create(ProductInterface::class);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Invalid argument provided for "$entity". Expected %s, received %s.',
                CategoryInterface::class,
                get_debug_type($invalidEntity),
            ),
        );

        $service = $this->instantiateTestObject();
        $service->execute($invalidEntity, $storeFixture->get());
    }
}
