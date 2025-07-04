<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Pipeline\Transformer;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\IndexingCategories\Pipeline\Transformer\ToCategoryUrl;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Model\Argument;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Transformer\TransformerInterface;
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\CategoryFixturePool;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers ToCategoryUrl
 * @method TransformerInterface instantiateTestObject(?array $arguments = null)
 * @method TransformerInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ToCategoryUrlTest extends TestCase
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

        $this->implementationFqcn = ToCategoryUrl::class;
        $this->interfaceFqcn = TransformerInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->categoryFixturePool = $this->objectManager->get(CategoryFixturePool::class);
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->websiteFixturesPool = $this->objectManager->create(WebsiteFixturesPool::class);
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

    public function testTransform_GeneratesCategoryUrl(): void
    {
        $store = $this->setupStoreAndBaseUrl(
            'test_store',
            'http://magento-test_store.loc/',
        );
        $storeId = (int)$store->getId();
        $this->createCategoryHierarchy($storeId);

        $bottomCategory = $this->categoryFixturePool->get('bottom_category');

        $transformer = $this->instantiateTestObject();
        $result = $transformer->transform(
            data: $bottomCategory->getCategory(),
            arguments: $this->getArgumentIterator($storeId),
        );

        $this->assertSame(
            'http://magento-test_store.loc/index.php/top-category/test-category/bottom-category.html',
            $result,
        );
    }

    /**
     * @return mixed[]
     */
    public function multipleStoreProvider(): array
    {
        return [
            'store_1' => ['test_store_1'],
            'store_2' => ['test_store_2'],
            'store_3' => ['test_store_3'],
        ];
    }

    /**
     * @dataProvider multipleStoreProvider
     */
    public function testTransform_GeneratesCorrectUrlForMultipleStores(string $storeKey): void
    {
        $baseUrl = "http://magento-" . $storeKey . ".loc/";
        $store = $this->setupStoreAndBaseUrl($storeKey, $baseUrl);

        $storeId = (int)$store->getId();
        $this->createCategory([
            'key' => 'top_cat_' . $storeId,
            'name' => 'Top Category ' . $storeId,
            'url_key' => 'top-category-' . $storeId,
            'store_id' => $storeId,
        ]);

        $category = $this->categoryFixturePool->get('top_cat_' . $storeId);

        $transformer = $this->instantiateTestObject();
        $result = $transformer->transform(
            data: $category->getCategory(),
            arguments: $this->getArgumentIterator($storeId),
        );

        $this->assertSame(
            $baseUrl . 'index.php/top-category-' . $storeId . '.html',
            $result,
        );
    }

    public function testTransform_GeneratesCorrectUrlForDifferentWebsites(): void
    {
        $websiteKeyFirst = 'website1';
        $this->createWebsite([
            'key' => $websiteKeyFirst,
            'code' => $websiteKeyFirst,
            'name' => ucfirst($websiteKeyFirst),
        ]);
        $websiteFixture1 = $this->websiteFixturesPool->get($websiteKeyFirst);

        $websiteKeySecond = 'website2';
        $this->createWebsite([
            'key' => $websiteKeySecond,
            'code' => $websiteKeySecond,
            'name' => ucfirst($websiteKeySecond),
        ]);
        $websiteFixture2 = $this->websiteFixturesPool->get($websiteKeySecond);

        $store1 = $this->setupStoreAndBaseUrl(
            'store1',
            'http://magento-store1.loc/',
            $websiteFixture1->getCode(),
        );
        $this->createCategory([
            'key' => 'website1_category',
            'name' => 'Website1 Category',
            'url_key' => 'website1-category',
            'store_id' => (int)$store1->getId(),
        ]);

        $transformer = $this->instantiateTestObject();
        $category1 = $this->categoryFixturePool->get('website1_category');
        $result1 = $transformer->transform(
            data: $category1->getCategory(),
            arguments: $this->getArgumentIterator((int)$store1->getId()),
        );
        $this->assertSame('http://magento-store1.loc/index.php/website1-category.html', $result1);

        $store2 = $this->setupStoreAndBaseUrl(
            'store2',
            'http://magento-store2.loc/',
            $websiteFixture2->getCode(),
        );
        $this->createCategory([
            'key' => 'website2_category',
            'name' => 'Website2 Category',
            'url_key' => 'website2-category',
            'store_id' => (int)$store2->getId(),
        ]);
        $category2 = $this->categoryFixturePool->get('website2_category');
        $result2 = $transformer->transform(
            data: $category2->getCategory(),
            arguments: $this->getArgumentIterator((int)$store2->getId()),
        );

        $this->assertSame('http://magento-store2.loc/index.php/website2-category.html', $result2);
    }

    /**
     * @param int $storeId
     *
     * @return ArgumentIterator
     */
    private function getArgumentIterator(int $storeId): ArgumentIterator
    {
        $argument = $this->objectManager->create(Argument::class, ['value' => $storeId, 'key' => 0]);

        return $this->objectManager->create(ArgumentIterator::class, ['data' => [$argument]]);
    }

    /**
     * @param Store $store
     *
     * @return void
     */
    private function setScope(Store $store): void
    {
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($store);

        $storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $storeManager->setCurrentStore($store);

        $urlBuilder = $this->objectManager->get(UrlInterface::class);
        $urlBuilder->setScope((int)$store->getId());
    }

    /**
     * @param string $storeKey
     * @param string $baseUrl
     * @param string|null $websiteCode
     *
     * @return Store
     * @throws \Exception
     */
    private function setupStoreAndBaseUrl(
        string $storeKey,
        string $baseUrl,
        ?string $websiteCode = null,
    ): StoreInterface {
        if ($websiteCode) {
            $this->createStore([
                'key' => $storeKey,
                'code' => $storeKey,
                'name' => ucfirst($storeKey),
                'website_code' => $websiteCode,
            ]);
        } else {
            $this->createStore([
                'key' => $storeKey,
                'code' => $storeKey,
                'name' => ucfirst($storeKey),

            ]);
        }

        $store = $this->storeFixturesPool->get($storeKey)->get();
        $this->setScope($store);

        foreach (
            [
                Store::XML_PATH_UNSECURE_BASE_LINK_URL,
                Store::XML_PATH_UNSECURE_BASE_URL,
                Store::XML_PATH_SECURE_BASE_LINK_URL,
                Store::XML_PATH_SECURE_BASE_URL,
            ] as $path
        ) {
            ConfigFixture::setForStore($path, $baseUrl, $store->getCode());
        }

        return $store;
    }

    /**
     * @param int $storeId
     *
     * @return void
     * @throws \Exception
     */
    private function createCategoryHierarchy(int $storeId): void
    {
        $this->createCategory([
            'key' => 'top_cat',
            'name' => 'Top Category',
            'url_key' => 'top-category',
            'store_id' => $storeId,
        ]);

        $topCategory = $this->categoryFixturePool->get('top_cat');

        $this->createCategory([
            'key' => 'test_category',
            'name' => 'Test Category',
            'url_key' => 'test-category',
            'parent' => $topCategory,
            'is_active' => false,
            'store_id' => $storeId,
        ]);

        $testCategory = $this->categoryFixturePool->get('test_category');

        $this->createCategory([
            'key' => 'bottom_category',
            'name' => 'Bottom Category',
            'url_key' => 'bottom-category',
            'parent' => $testCategory,
            'store_id' => $storeId,
        ]);
    }
}
