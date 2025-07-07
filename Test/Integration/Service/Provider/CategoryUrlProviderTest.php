<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Service\Provider;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\IndexingCategories\Service\Provider\CategoryUrlProvider;
use Klevu\IndexingCategories\Service\Provider\CategoryUrlProviderInterface;
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Catalog\Model\Category;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\Store;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\UrlRewrite\Model\ResourceModel\UrlRewrite as UrlRewriteResource;
use Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollection;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\CategoryFixturePool;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers CategoryUrlProvider
 * @method CategoryUrlProvider instantiateTestObject(?array $arguments = null)
 * @method CategoryUrlProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class CategoryUrlProviderTest extends TestCase
{
    use CategoryTrait;
    use ObjectInstantiationTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;
    use WebsiteTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var ScopeProviderInterface|null
     */
    private ?ScopeProviderInterface $scopeProvider = null;
    /**
     * @var ScopeConfigInterface|null
     */
    private ?ScopeConfigInterface $scopeConfig = null;
    /**
     * @var WriterInterface|null
     */
    private ?WriterInterface $configWriter = null;
    /**
     * @var array<string, mixed>
     */
    private array $origConfig = [];

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = CategoryUrlProvider::class;
        $this->interfaceFqcn = CategoryUrlProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $this->scopeConfig = $this->objectManager->get(ScopeConfigInterface::class);
        $this->configWriter = $this->objectManager->get(WriterInterface::class);

        $this->categoryFixturePool = $this->objectManager->get(CategoryFixturePool::class);
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->websiteFixturesPool = $this->objectManager->get(WebsiteFixturesPool::class);

        $this->origConfig = [
            Store::XML_PATH_STORE_IN_URL => $this->scopeConfig->getValue(
                Store::XML_PATH_STORE_IN_URL,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                0,
            ),
        ];
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->storeFixturesPool->rollback();
        $this->websiteFixturesPool->rollback();
        $this->categoryFixturePool->rollback();

        // Workaround for magentoDbIsolation not functioning correctly
        foreach ($this->origConfig as $path => $value) {
            $this->configWriter->save(
                path: $path,
                value: $value,
                scope: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                scopeId: 0,
            );
        }
        $this->origConfig = [];
    }

    /**
     * @return mixed[]
     */
    public static function dataProvider_testGetForCategoryId(): array
    {
        // phpcs:disable Generic.Files.LineLength.TooLong
        return [
            'withoutStoreCode_withoutCategoryUrlSuffix' => [
                'useStoreCodeInUrl' => false,
                'categoryUrlSuffix' => '',
                'expectedResults' => [
                    // Store 1 : Domain 1; No subdirectory; no webserver rewrites; no secure in front
                    'http://link.domain1.test/index.php/indexing-categories-url-provider-child-category-store-1',
                    // Store 2 : Domain 1; Subdirectory; no webserver rewrites; secure in front
                    'https://link.domain1.test/subdirectory/index.php/indexing-categories-url-provider-child-category-store-2',
                    // Store 3 : Domain 2; No subdirectory; webserver rewrites; no secure in front
                    'http://link.domain2.test/indexing-categories-url-provider-child-category-store-3',
                    // Store 4 : Domain 2; Subdirectory; webserver rewrites; secure in front
                    'https://link.domain2.test/subdirectory/indexing-categories-url-provider-child-category-store-4',
                ],
            ],
            'withoutStoreCode_withCategoryUrlSuffix' => [
                'useStoreCodeInUrl' => false,
                'categoryUrlSuffix' => '.htm',
                'expectedResults' => [
                    // Store 1 : Domain 1; No subdirectory; no webserver rewrites; no secure in front
                    'http://link.domain1.test/index.php/indexing-categories-url-provider-child-category-store-1.htm',
                    // Store 2 : Domain 1; Subdirectory; no webserver rewrites; secure in front
                    'https://link.domain1.test/subdirectory/index.php/indexing-categories-url-provider-child-category-store-2.htm',
                    // Store 3 : Domain 2; No subdirectory; webserver rewrites; no secure in front
                    'http://link.domain2.test/indexing-categories-url-provider-child-category-store-3.htm',
                    // Store 4 : Domain 2; Subdirectory; webserver rewrites; secure in front
                    'https://link.domain2.test/subdirectory/indexing-categories-url-provider-child-category-store-4.htm',
                ],
            ],
            'withStoreCode_withoutCategoryUrlSuffix' => [
                'useStoreCodeInUrl' => true,
                'categoryUrlSuffix' => '',
                'expectedResults' => [
                    // Store 1 : Domain 1; No subdirectory; no webserver rewrites; no secure in front
                    'http://link.domain1.test/index.php/klevu_indcat_urlprov_store1/indexing-categories-url-provider-child-category-store-1',
                    // Store 2 : Domain 1; Subdirectory; no webserver rewrites; secure in front
                    'https://link.domain1.test/subdirectory/index.php/klevu_indcat_urlprov_store2/indexing-categories-url-provider-child-category-store-2',
                    // Store 3 : Domain 2; No subdirectory; webserver rewrites; no secure in front
                    'http://link.domain2.test/klevu_indcat_urlprov_store3/indexing-categories-url-provider-child-category-store-3',
                    // Store 4 : Domain 2; Subdirectory; webserver rewrites; secure in front
                    'https://link.domain2.test/subdirectory/klevu_indcat_urlprov_store4/indexing-categories-url-provider-child-category-store-4',
                ],
            ],
            'withStoreCode_withCategoryUrlSuffix' => [
                'useStoreCodeInUrl' => true,
                'categoryUrlSuffix' => '.htm',
                'expectedResults' => [
                    // Store 1 : Domain 1; No subdirectory; no webserver rewrites; no secure in front
                    'http://link.domain1.test/index.php/klevu_indcat_urlprov_store1/indexing-categories-url-provider-child-category-store-1.htm',
                    // Store 2 : Domain 1; Subdirectory; no webserver rewrites; secure in front
                    'https://link.domain1.test/subdirectory/index.php/klevu_indcat_urlprov_store2/indexing-categories-url-provider-child-category-store-2.htm',
                    // Store 3 : Domain 2; No subdirectory; webserver rewrites; no secure in front
                    'http://link.domain2.test/klevu_indcat_urlprov_store3/indexing-categories-url-provider-child-category-store-3.htm',
                    // Store 4 : Domain 2; Subdirectory; webserver rewrites; secure in front
                    'https://link.domain2.test/subdirectory/klevu_indcat_urlprov_store4/indexing-categories-url-provider-child-category-store-4.htm',
                ],
            ],
        ];
        // phpcs:enable Generic.Files.LineLength.TooLong
    }

    /**
     * @param bool $useStoreCodeInUrl
     * @param string $categoryUrlSuffix
     * @param mixed[] $expectedResults
     *
     * @return void
     *
     * @dataProvider dataProvider_testGetForCategoryId
     * @group smoke
     * @magentoDbIsolation enabled
     * @group wipm
     */
    public function testGetForCategoryId(
        bool $useStoreCodeInUrl,
        string $categoryUrlSuffix,
        array $expectedResults,
    ): void {
        ConfigFixture::setGlobal(
            path: 'web/url/use_store',
            value: 0,
        );
        ConfigFixture::setGlobal(
            path: 'web/unsecure/base_url',
            value: 'http://base.domain-global.test/',
        );
        ConfigFixture::setGlobal(
            path: 'web/unsecure/base_link_url',
            value: 'http://link.domain-global.test/',
        );
        ConfigFixture::setGlobal(
            path: 'web/secure/base_url',
            value: 'https://base.domain-global.test/',
        );
        ConfigFixture::setGlobal(
            path: 'web/secure/base_link_url',
            value: 'https://link.domain-global.test/',
        );
        ConfigFixture::setGlobal(
            path: 'catalog/seo/category_url_suffix',
            value: $categoryUrlSuffix,
        );

        // Config Fixtures don't work for this
        $this->configWriter->save(
            path: Store::XML_PATH_STORE_IN_URL,
            value: $useStoreCodeInUrl ? '1' : '0',
            scope: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            scopeId: 0,
        );

        $this->createWebsite(
            websiteData: [
                'key' => 'klevu_indcat_urlprov_website1',
                'code' => 'klevu_indcat_urlprov_website1',
                'name' => 'Indexing Categories: Url Provider Website 1',
            ],
        );
        $websiteFixture1 = $this->websiteFixturesPool->get('klevu_indcat_urlprov_website1');
        
        $this->createWebsite(
            websiteData: [
                'key' => 'klevu_indcat_urlprov_website2',
                'code' => 'klevu_indcat_urlprov_website2',
                'name' => 'Indexing Categories: Url Provider Website 2',
            ],
        );
        $websiteFixture2 = $this->websiteFixturesPool->get('klevu_indcat_urlprov_website2');
        
        // Store 1 : Domain 1; No subdirectory; no webserver rewrites; no secure in front
        $this->createStore(
            storeData: [
                'key' => 'klevu_indcat_urlprov_store1',
                'code' => 'klevu_indcat_urlprov_store1',
                'name' => 'Indexing Categories: Url Provider Store 1',
                'website_id' => $websiteFixture1->getId(),
                'is_active' => true,
            ],
        );
        $storeFixture1 = $this->storeFixturesPool->get('klevu_indcat_urlprov_store1');
        ConfigFixture::setForStore(
            path: 'web/unsecure/base_url',
            value: 'http://base.domain1.test/',
            storeCode: 'klevu_indcat_urlprov_store1',
        );
        ConfigFixture::setForStore(
            path: 'web/unsecure/base_link_url',
            value: 'http://link.domain1.test/',
            storeCode: 'klevu_indcat_urlprov_store1',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/base_url',
            value: 'https://base.domain1.test/',
            storeCode: 'klevu_indcat_urlprov_store1',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/base_link_url',
            value: 'https://link.domain1.test/',
            storeCode: 'klevu_indcat_urlprov_store1',
        );
        ConfigFixture::setForStore(
            path: 'web/seo/use_rewrites',
            value: 0,
            storeCode: 'klevu_indcat_urlprov_store1',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/use_in_frontend',
            value: 0,
            storeCode: 'klevu_indcat_urlprov_store1',
        );
        ConfigFixture::setForStore(
            path: 'catalog/seo/category_url_suffix',
            value: $categoryUrlSuffix,
            storeCode: 'klevu_indcat_urlprov_store1',
        );

        // Store 2 : Domain 1; Subdirectory; no webserver rewrites; secure in front
        $this->createStore(
            storeData: [
                'key' => 'klevu_indcat_urlprov_store2',
                'code' => 'klevu_indcat_urlprov_store2',
                'name' => 'Indexing Categories: Url Provider Store 2',
                'website_id' => $websiteFixture1->getId(),
                'is_active' => true,
            ],
        );
        $storeFixture2 = $this->storeFixturesPool->get('klevu_indcat_urlprov_store2');
        ConfigFixture::setForStore(
            path: 'web/unsecure/base_url',
            value: 'http://base.domain1.test/subdirectory/',
            storeCode: 'klevu_indcat_urlprov_store2',
        );
        ConfigFixture::setForStore(
            path: 'web/unsecure/base_link_url',
            value: 'http://link.domain1.test/subdirectory/',
            storeCode: 'klevu_indcat_urlprov_store2',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/base_url',
            value: 'https://base.domain1.test/subdirectory/',
            storeCode: 'klevu_indcat_urlprov_store2',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/base_link_url',
            value: 'https://link.domain1.test/subdirectory/',
            storeCode: 'klevu_indcat_urlprov_store2',
        );
        ConfigFixture::setForStore(
            path: 'web/seo/use_rewrites',
            value: 0,
            storeCode: 'klevu_indcat_urlprov_store2',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/use_in_frontend',
            value: 1,
            storeCode: 'klevu_indcat_urlprov_store2',
        );
        ConfigFixture::setForStore(
            path: 'catalog/seo/category_url_suffix',
            value: $categoryUrlSuffix,
            storeCode: 'klevu_indcat_urlprov_store2',
        );

        // Store 3 : Domain 2; No subdirectory; webserver rewrites; no secure in front
        $this->createStore(
            storeData: [
                'key' => 'klevu_indcat_urlprov_store3',
                'code' => 'klevu_indcat_urlprov_store3',
                'name' => 'Indexing Categories: Url Provider Store 3',
                'website_id' => $websiteFixture2->getId(),
                'is_active' => true,
            ],
        );
        $storeFixture3 = $this->storeFixturesPool->get('klevu_indcat_urlprov_store3');
        ConfigFixture::setForStore(
            path: 'web/unsecure/base_url',
            value: 'http://base.domain2.test/',
            storeCode: 'klevu_indcat_urlprov_store3',
        );
        ConfigFixture::setForStore(
            path: 'web/unsecure/base_link_url',
            value: 'http://link.domain2.test/',
            storeCode: 'klevu_indcat_urlprov_store3',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/base_url',
            value: 'https://base.domain2.test/',
            storeCode: 'klevu_indcat_urlprov_store3',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/base_link_url',
            value: 'https://link.domain2.test/',
            storeCode: 'klevu_indcat_urlprov_store3',
        );
        ConfigFixture::setForStore(
            path: 'web/seo/use_rewrites',
            value: 1,
            storeCode: 'klevu_indcat_urlprov_store3',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/use_in_frontend',
            value: 0,
            storeCode: 'klevu_indcat_urlprov_store3',
        );
        ConfigFixture::setForStore(
            path: 'catalog/seo/category_url_suffix',
            value: $categoryUrlSuffix,
            storeCode: 'klevu_indcat_urlprov_store3',
        );

        // Store 4 : Domain 2; Subdirectory; webserver rewrites; secure in front
        $this->createStore(
            storeData: [
                'key' => 'klevu_indcat_urlprov_store4',
                'code' => 'klevu_indcat_urlprov_store4',
                'name' => 'Indexing Categories: Url Provider Store 4',
                'website_id' => $websiteFixture2->getId(),
                'is_active' => true,
            ],
        );
        $storeFixture4 = $this->storeFixturesPool->get('klevu_indcat_urlprov_store4');
        ConfigFixture::setForStore(
            path: 'web/unsecure/base_url',
            value: 'http://base.domain2.test/subdirectory/',
            storeCode: 'klevu_indcat_urlprov_store4',
        );
        ConfigFixture::setForStore(
            path: 'web/unsecure/base_link_url',
            value: 'http://link.domain2.test/subdirectory/',
            storeCode: 'klevu_indcat_urlprov_store4',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/base_url',
            value: 'https://base.domain2.test/subdirectory/',
            storeCode: 'klevu_indcat_urlprov_store4',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/base_link_url',
            value: 'https://link.domain2.test/subdirectory/',
            storeCode: 'klevu_indcat_urlprov_store4',
        );
        ConfigFixture::setForStore(
            path: 'web/seo/use_rewrites',
            value: 1,
            storeCode: 'klevu_indcat_urlprov_store4',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/use_in_frontend',
            value: 1,
            storeCode: 'klevu_indcat_urlprov_store4',
        );
        ConfigFixture::setForStore(
            path: 'catalog/seo/category_url_suffix',
            value: $categoryUrlSuffix,
            storeCode: 'klevu_indcat_urlprov_store4',
        );

        $this->createCategory(
            categoryData: [
                'key' => 'klevu_indcat_urlprov_category1',
                'name' => 'Indexing Categories: Url Provider Parent Category',
                'is_active' => true,
                'stores' => [
                    $storeFixture1->getId() => [
                        'name' => 'Indexing Categories: Url Provider Parent Category Store 1',
                        'is_active' => true,
                        'url_key' => 'indexing-categories-url-provider-parent-category-store-1',
                    ],
                    $storeFixture2->getId() => [
                        'name' => 'Indexing Categories: Url Provider Parent Category Store 2',
                        'is_active' => true,
                        'url_key' => 'indexing-categories-url-provider-parent-category-store-2',
                    ],
                    $storeFixture3->getId() => [
                        'name' => 'Indexing Categories: Url Provider Parent Category Store 3',
                        'is_active' => true,
                        'url_key' => 'indexing-categories-url-provider-parent-category-store-3',
                    ],
                    $storeFixture4->getId() => [
                        'name' => 'Indexing Categories: Url Provider Parent Category Store 4',
                        'is_active' => true,
                        'url_key' => 'indexing-categories-url-provider-parent-category-store-4',
                    ],
                ],
            ],
        );
        $parentCategoryFixture = $this->categoryFixturePool->get('klevu_indcat_urlprov_category1');

        $this->createCategory(
            categoryData: [
                'key' => 'klevu_indcat_urlprov_category2',
                'name' => 'Indexing Categories: Url Provider Child Category',
                'is_active' => true,
                'parent_id' => $parentCategoryFixture->getId(),
                'stores' => [
                    $storeFixture1->getId() => [
                        'name' => 'Indexing Categories: Url Provider Child Category Store 1',
                        'is_active' => true,
                        'url_key' => 'indexing-categories-url-provider-child-category-store-1',
                    ],
                    $storeFixture2->getId() => [
                        'name' => 'Indexing Categories: Url Provider Child Category Store 2',
                        'is_active' => true,
                        'url_key' => 'indexing-categories-url-provider-child-category-store-2',
                    ],
                    $storeFixture3->getId() => [
                        'name' => 'Indexing Categories: Url Provider Child Category Store 3',
                        'is_active' => true,
                        'url_key' => 'indexing-categories-url-provider-child-category-store-3',
                    ],
                    $storeFixture4->getId() => [
                        'name' => 'Indexing Categories: Url Provider Child Category Store 4',
                        'is_active' => true,
                        'url_key' => 'indexing-categories-url-provider-child-category-store-4',
                    ],
                ],
            ],
        );
        $childCategoryFixture = $this->categoryFixturePool->get('klevu_indcat_urlprov_category2');

        $categoryUrlProvider = $this->instantiateTestObject([]); // Force create to avoid cache issues

        $url1 = $categoryUrlProvider->getForCategoryId(
            categoryId: $childCategoryFixture->getId(),
            storeId: $storeFixture1->getId(),
        );
        $url2 = $categoryUrlProvider->getForCategoryId(
            categoryId: $childCategoryFixture->getId(),
            storeId: $storeFixture2->getId(),
        );
        $url3 = $categoryUrlProvider->getForCategoryId(
            categoryId: $childCategoryFixture->getId(),
            storeId: $storeFixture3->getId(),
        );
        $url4 = $categoryUrlProvider->getForCategoryId(
            categoryId: $childCategoryFixture->getId(),
            storeId: $storeFixture4->getId(),
        );

        $this->assertSame(
            expected: $expectedResults[0],
            actual: $url1,
            message: 'Store 1 : Domain 1; No subdirectory; no webserver rewrites; no secure in front',
        );
        $this->assertSame(
            expected: $expectedResults[1],
            actual: $url2,
            message: 'Store 2 : Domain 1; Subdirectory; no webserver rewrites; secure in front',
        );
        $this->assertSame(
            expected: $expectedResults[2],
            actual: $url3,
            message: 'Store 3 : Domain 2; No subdirectory; webserver rewrites; no secure in front',
        );
        $this->assertSame(
            expected: $expectedResults[3],
            actual: $url4,
            message: 'Store 4 : Domain 2; Subdirectory; webserver rewrites; secure in front',
        );
    }

    public function testGetForCategory_WhereCategoryHasRequestPath(): void
    {
        ConfigFixture::setGlobal(
            path: 'catalog/seo/category_url_suffix',
            value: '.htm',
        );
        ConfigFixture::setGlobal(
            path: 'web/url/use_store',
            value: 0,
        );
        ConfigFixture::setGlobal(
            path: 'web/seo/use_rewrites',
            value: 1,
        );
        ConfigFixture::setGlobal(
            path: 'web/secure/use_in_frontend',
            value: 1,
        );
        // Config Fixtures don't work for this
        $this->configWriter->save(
            path: Store::XML_PATH_STORE_IN_URL,
            value: '0',
            scope: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            scopeId: 0,
        );

        $this->createWebsite(
            websiteData: [
                'key' => 'klevu_indcat_urlprov_website_rp',
                'code' => 'klevu_indcat_urlprov_website_rp',
                'name' => 'Indexing Categories: Url Provider Request Path',
            ],
        );
        $websiteFixture = $this->websiteFixturesPool->get('klevu_indcat_urlprov_website_rp');

        $this->createStore(
            storeData: [
                'key' => 'klevu_indcat_urlprov_store_rp',
                'code' => 'klevu_indcat_urlprov_store_rp',
                'name' => 'Indexing Categories: Url Provider Request Path',
                'website_id' => $websiteFixture->getId(),
                'is_active' => true,
            ],
        );
        $storeFixture = $this->storeFixturesPool->get('klevu_indcat_urlprov_store_rp');
        ConfigFixture::setForStore(
            path: 'catalog/seo/category_url_suffix',
            value: '.htm',
            storeCode: 'klevu_indcat_urlprov_store_rp',
        );
        ConfigFixture::setForStore(
            path: 'web/url/use_store',
            value: 0,
            storeCode: 'klevu_indcat_urlprov_store_rp',
        );
        ConfigFixture::setForStore(
            path: 'web/seo/use_rewrites',
            value: 1,
            storeCode: 'klevu_indcat_urlprov_store_rp',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/use_in_frontend',
            value: 1,
            storeCode: 'klevu_indcat_urlprov_store_rp',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/base_link_url',
            value: 'https://link.domain1.test/',
            storeCode: 'klevu_indcat_urlprov_store_rp',
        );

        $this->createCategory(
            categoryData: [
                'key' => 'klevu_indcat_urlprov_cat_rp',
                'name' => 'Indexing Categories: Url Provider Category With Request Path',
                'url_key' => 'klevu-indcat-urlprov-cat-rp',
                'url_path' => 'klevu-indcat-urlprov-cat-rp',
                'is_active' => true,
            ],
        );
        $categoryFixture = $this->categoryFixturePool->get('klevu_indcat_urlprov_cat_rp');
        /** @var Category $category */
        $category = $categoryFixture->getCategory();
        $this->assertNull($category->getRequestPath());

        $categoryUrlProvider = $this->instantiateTestObject([]); // Force create to avoid cache issues

        $categoryUrl = $categoryUrlProvider->getForCategory(
            category: $category,
            storeId: (int)$storeFixture->getId(),
        );
        $this->assertSame(
            expected: 'https://link.domain1.test/klevu-indcat-urlprov-cat-rp.htm',
            actual: $categoryUrl,
        );

        $categoryUrlProvider = $this->instantiateTestObject([]); // Force create to avoid cache issues

        $category->setData(
            key: 'request_path',
            value: 'overwritten-request-path',
        );
        $categoryUrl = $categoryUrlProvider->getForCategory(
            category: $category,
            storeId: (int)$storeFixture->getId(),
        );
        $this->assertSame(
            expected: 'https://link.domain1.test/overwritten-request-path',
            actual: $categoryUrl,
        );
    }

    public function testGetForCategory_WhereCategoryDoesNotHaveUrlRewrite(): void
    {
        ConfigFixture::setGlobal(
            path: 'catalog/seo/category_url_suffix',
            value: '.htm',
        );
        ConfigFixture::setGlobal(
            path: 'web/url/use_store',
            value: 0,
        );
        ConfigFixture::setGlobal(
            path: 'web/seo/use_rewrites',
            value: 1,
        );
        ConfigFixture::setGlobal(
            path: 'web/secure/use_in_frontend',
            value: 1,
        );
        // Config Fixtures don't work for this
        $this->configWriter->save(
            path: Store::XML_PATH_STORE_IN_URL,
            value: '0',
            scope: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            scopeId: 0,
        );

        $this->createWebsite(
            websiteData: [
                'key' => 'klevu_indcat_urlprov_website_ur',
                'code' => 'klevu_indcat_urlprov_website_ur',
                'name' => 'Indexing Categories: Url Provider Url Rewrite',
            ],
        );
        $websiteFixture = $this->websiteFixturesPool->get('klevu_indcat_urlprov_website_ur');

        $this->createStore(
            storeData: [
                'key' => 'klevu_indcat_urlprov_store_ur',
                'code' => 'klevu_indcat_urlprov_store_ur',
                'name' => 'Indexing Categories: Url Provider Url Rewrite',
                'website_id' => $websiteFixture->getId(),
                'is_active' => true,
            ],
        );
        $storeFixture = $this->storeFixturesPool->get('klevu_indcat_urlprov_store_ur');
        ConfigFixture::setForStore(
            path: 'catalog/seo/category_url_suffix',
            value: '.htm',
            storeCode: 'klevu_indcat_urlprov_store_ur',
        );
        ConfigFixture::setForStore(
            path: 'web/url/use_store',
            value: 0,
            storeCode: 'klevu_indcat_urlprov_store_ur',
        );
        ConfigFixture::setForStore(
            path: 'web/seo/use_rewrites',
            value: 1,
            storeCode: 'klevu_indcat_urlprov_store_ur',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/use_in_frontend',
            value: 1,
            storeCode: 'klevu_indcat_urlprov_store_ur',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/base_link_url',
            value: 'https://link.domain1.test/',
            storeCode: 'klevu_indcat_urlprov_store_ur',
        );

        $this->createCategory(
            categoryData: [
                'key' => 'klevu_indcat_urlprov_cat_ur',
                'name' => 'Indexing Categories: Url Provider Category Without Url Rewrite',
                'url_key' => 'klevu-indcat-urlprov-cat-ur',
                'url_path' => 'klevu-indcat-urlprov-cat-ur',
                'is_active' => true,
            ],
        );
        $categoryFixture = $this->categoryFixturePool->get('klevu_indcat_urlprov_cat_ur');
        /** @var Category $category */
        $category = $categoryFixture->getCategory();
        $this->assertNull($category->getRequestPath());

        $categoryUrlProvider = $this->instantiateTestObject([]); // Force create to avoid cache issues

        $categoryUrl = $categoryUrlProvider->getForCategory(
            category: $category,
            storeId: (int)$storeFixture->getId(),
        );
        $this->assertSame(
            expected: 'https://link.domain1.test/klevu-indcat-urlprov-cat-ur.htm',
            actual: $categoryUrl,
            message: 'With URL Rewrite',
        );

        $categoryUrlProvider = $this->instantiateTestObject([]); // Force create to avoid cache issues

        $urlRewriteCollection = $this->objectManager->get(
            type: UrlRewriteCollection::class,
        );
        $urlRewriteResource = $this->objectManager->get(
            type: UrlRewriteResource::class,
        );

        $urlRewriteCollection->addFieldToFilter(
            field: 'entity_type',
            condition: 'category',
        );
        $urlRewriteCollection->addFieldToFilter(
            field: 'entity_id',
            condition: $category->getId(),
        );
        foreach ($urlRewriteCollection as $urlRewrite) {
            $urlRewriteResource->delete($urlRewrite);
        }

        $categoryUrl = $categoryUrlProvider->getForCategory(
            category: $category,
            storeId: (int)$storeFixture->getId(),
        );
        $this->assertSame(
            expected: sprintf(
                'https://link.domain1.test/catalog/category/view/s/%s/id/%d/',
                'klevu-indcat-urlprov-cat-ur',
                $category->getId(),
            ),
            actual: $categoryUrl,
            message: 'Without URL Rewrite; With URL Key',
        );

        $categoryUrlProvider = $this->instantiateTestObject([]); // Force create to avoid cache issues
        $category->unsetData('url_key');
        $category->unsetData('url_path');

        $categoryUrl = $categoryUrlProvider->getForCategory(
            category: $category,
            storeId: (int)$storeFixture->getId(),
        );
        $this->assertSame(
            expected: sprintf(
                'https://link.domain1.test/catalog/category/view/s/%s/id/%d/',
                'indexing-categories-url-provider-category-without-url-rewrite',
                $category->getId(),
            ),
            actual: $categoryUrl,
            message: 'Without URL Rewrite; Without URL Key',
        );
    }

    public function testGetForCategory_CachesResponsesCorrectly(): void
    {
        ConfigFixture::setGlobal(
            path: 'catalog/seo/category_url_suffix',
            value: '.htm',
        );
        ConfigFixture::setGlobal(
            path: 'web/url/use_store',
            value: 0,
        );
        ConfigFixture::setGlobal(
            path: 'web/seo/use_rewrites',
            value: 1,
        );
        ConfigFixture::setGlobal(
            path: 'web/secure/use_in_frontend',
            value: 1,
        );
        // Config Fixtures don't work for this
        $this->configWriter->save(
            path: Store::XML_PATH_STORE_IN_URL,
            value: '0',
            scope: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            scopeId: 0,
        );

        $this->createWebsite(
            websiteData: [
                'key' => 'klevu_indcat_urlprov_website_ca',
                'code' => 'klevu_indcat_urlprov_website_ca',
                'name' => 'Indexing Categories: Url Provider Caching',
            ],
        );
        $websiteFixture = $this->websiteFixturesPool->get('klevu_indcat_urlprov_website_ca');

        $this->createStore(
            storeData: [
                'key' => 'klevu_indcat_urlprov_store_ca',
                'code' => 'klevu_indcat_urlprov_store_ca',
                'name' => 'Indexing Categories: Url Provider Caching',
                'website_id' => $websiteFixture->getId(),
                'is_active' => true,
            ],
        );
        $storeFixture = $this->storeFixturesPool->get('klevu_indcat_urlprov_store_ca');
        ConfigFixture::setForStore(
            path: 'catalog/seo/category_url_suffix',
            value: '.htm',
            storeCode: 'klevu_indcat_urlprov_store_ca',
        );
        ConfigFixture::setForStore(
            path: 'web/url/use_store',
            value: 0,
            storeCode: 'klevu_indcat_urlprov_store_ca',
        );
        ConfigFixture::setForStore(
            path: 'web/seo/use_rewrites',
            value: 1,
            storeCode: 'klevu_indcat_urlprov_store_ca',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/use_in_frontend',
            value: 1,
            storeCode: 'klevu_indcat_urlprov_store_ca',
        );
        ConfigFixture::setForStore(
            path: 'web/secure/base_link_url',
            value: 'https://link.domain1.test/',
            storeCode: 'klevu_indcat_urlprov_store_ca',
        );

        $this->createCategory(
            categoryData: [
                'key' => 'klevu_indcat_urlprov_cat_ca',
                'name' => 'Indexing Categories: Url Provider Category Cache Test',
                'request_path' => 'klevu-indcat-urlprov-cat-ca',
                'url_key' => 'klevu-indcat-urlprov-cat-ca',
                'url_path' => 'klevu-indcat-urlprov-cat-ca',
                'is_active' => true,
            ],
        );
        $categoryFixture = $this->categoryFixturePool->get('klevu_indcat_urlprov_cat_ca');
        /** @var Category $category */
        $category = $categoryFixture->getCategory();
        $this->assertNull($category->getRequestPath());

        /** @var CategoryUrlProvider $categoryUrlProvider */
        $categoryUrlProvider = $this->instantiateTestObject([]); // Force create to avoid cache issues

        // Get URL
        $categoryUrl = $categoryUrlProvider->getForCategory(
            category: $category,
            storeId: (int)$storeFixture->getId(),
        );
        $this->assertSame(
            expected: 'https://link.domain1.test/klevu-indcat-urlprov-cat-ca.htm',
            actual: $categoryUrl,
            message: 'With URL Rewrite',
        );
        
        // Change Category Data
        $category->setData(
            key: 'request_path',
            value: 'request-path-updated',
        );

        // Get URL again
        $categoryUrl = $categoryUrlProvider->getForCategory(
            category: $category,
            storeId: (int)$storeFixture->getId(),
        );
        $this->assertSame(
            expected: 'https://link.domain1.test/klevu-indcat-urlprov-cat-ca.htm',
            actual: $categoryUrl,
            message: 'With URL Rewrite',
        );

        // Clear cache
        $categoryUrlProvider->clearCache();

        // Get URL again
        $categoryUrl = $categoryUrlProvider->getForCategory(
            category: $category,
            storeId: (int)$storeFixture->getId(),
        );
        $this->assertSame(
            expected: 'https://link.domain1.test/request-path-updated',
            actual: $categoryUrl,
            message: 'With URL Rewrite',
        );
    }
}
