<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Pipeline\Transformer;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\IndexingCategories\Pipeline\Transformer\ToCategoryPath;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Transformer\TransformerInterface;
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\CategoryFixturePool;

/**
 * @covers ToCategoryPath
 * @method TransformerInterface instantiateTestObject(?array $arguments = null)
 * @method TransformerInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ToCategoryPathTest extends TestCase
{
    use CategoryTrait;
    use ObjectInstantiationTrait;
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

        $this->implementationFqcn = ToCategoryPath::class;
        $this->interfaceFqcn = TransformerInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->categoryFixturePool = $this->objectManager->get(CategoryFixturePool::class);
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->categoryFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    public function testTransform_ReturnsNull_WhenNotDataProvided(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

        $provider = $this->instantiateTestObject();
        $result = $provider->transform(data: null);

        $this->assertNull(actual: $result);
    }

    /**
     * @testWith ["string"]
     *           ["1234a"]
     *           [[12]]
     *           [true]
     */
    public function testTransform_ThrowsException_WhenDataNotNumeric(mixed $invalidData): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

        $this->expectException(InvalidInputDataException::class);

        $provider = $this->instantiateTestObject();
        $provider->transform(data: $invalidData);
    }

    public function testTransform_ReturnsString(): void
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
        $result = $provider->transform(
            data: $bottomCategoryFixture->getId(),
        );

        $this->assertSame(
            expected: 'Top Category;Test Category;Bottom Category',
            actual: $result,
        );
    }
}
