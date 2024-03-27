<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Service;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Configuration\Service\Provider\Sdk\BaseUrlsProvider;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Service\AttributeSyncOrchestratorService;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Api\Data\SyncResultInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\AttributeSyncOrchestratorServiceInterface;
use Klevu\IndexingProducts\Model\Source\Aspect;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\CategoryAttributeInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers AttributeSyncOrchestratorService
 * @method AttributeSyncOrchestratorServiceInterface instantiateTestObject(?array $arguments = null)
 * @method AttributeSyncOrchestratorServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AttributeSyncOrchestratorServiceTest extends TestCase
{
    use AttributeTrait;
    use IndexingAttributesTrait;
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

        $this->implementationFqcn = AttributeSyncOrchestratorService::class;
        $this->interfaceFqcn = AttributeSyncOrchestratorServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->attributeFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu/indexing/image_width_product 800
     * @magentoConfigFixture klevu_test_store_1_store klevu/indexing/image_height_product 800
     */
    public function testExecute_ForRealApiKeys(): void
    {
        /**
         * This test requires your Klevu API keys
         * These API keys can be set in dev/tests/integration/phpunit.xml
         * <phpunit>
         *     <testsuites>
         *      ...
         *     </testsuites>
         *     <php>
         *         ...
         *         <env name="KLEVU_JS_API_KEY" value="" force="true" />
         *         <env name="KLEVU_REST_API_KEY" value="" force="true" />
         *         <env name="KLEVU_API_REST_URL" value="api.ksearchnet.com" force="true" />
         *         // KLEVU_TIERS_URL only required for none production env
         *         <env name="KLEVU_TIERS_URL" value="tiers.klevu.com" force="true" />
         *     </php>
         */
        $restApiKey = getenv('KLEVU_REST_API_KEY');
        $jsApiKey = getenv('KLEVU_JS_API_KEY');
        $restApiUrl = getenv('KLEVU_REST_API_URL');
        $tiersApiUrl = getenv('KLEVU_TIERS_URL');
        $indexingUrl = getenv('KLEVU_INDEXING_URL');
        if (!$restApiKey || !$jsApiKey || !$restApiUrl || !$tiersApiUrl || !$indexingUrl) {
            $this->markTestSkipped('Klevu API keys are not set in `dev/tests/integration/phpunit.xml`. Test Skipped');
        }

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $jsApiKey,
            restAuthKey: $restApiKey,
        );

        ConfigFixture::setForStore(
            path: BaseUrlsProvider::CONFIG_XML_PATH_URL_INDEXING,
            value: $indexingUrl,
            storeCode: $storeFixture->getCode(),
        );
        ConfigFixture::setForStore(
            path: BaseUrlsProvider::CONFIG_XML_PATH_URL_API,
            value: $restApiUrl,
            storeCode: $storeFixture->getCode(),
        );
        ConfigFixture::setForStore(
            path: BaseUrlsProvider::CONFIG_XML_PATH_URL_TIERS,
            value: $tiersApiUrl,
            storeCode: $storeFixture->getCode(),
        );
        $scopeProvider->unsetCurrentScope();

        $this->createAttribute([
            'key' => 'test_attribute_1',
            'entity_type' => CategoryAttributeInterface::ENTITY_TYPE_CODE,
            'code' => 'klevu_test_category_attribute_1',
            'index_as' => IndexType::INDEX,
            'aspect' => Aspect::ATTRIBUTES,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute_1');

        $this->cleanIndexingAttributes(apiKey: $jsApiKey);
        $this->createIndexingAttribute(data: [
            IndexingAttribute::TARGET_ID => $attributeFixture->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture->getAttributeCode(),
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => $jsApiKey,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $service = $this->instantiateTestObject();
        $responses = $service->execute(
            attributeType: 'KLEVU_CATEGORY',
            apiKey: $jsApiKey,
        );

        $this->assertCount(expectedCount: 1, haystack: $responses);
        $this->assertArrayHasKey(key: $jsApiKey, array: $responses);

        $this->assertGreaterThanOrEqual(expected: 1, actual: count($responses[$jsApiKey]));
        $this->assertArrayHasKey(key: 'KLEVU_CATEGORY::add', array: $responses[$jsApiKey]);

        $this->assertGreaterThanOrEqual(expected: 1, actual: count($responses[$jsApiKey]['KLEVU_CATEGORY::add']));
        $this->assertArrayHasKey(
            key: 'cat__' . $attributeFixture->getAttributeCode(),
            array: $responses[$jsApiKey]['KLEVU_CATEGORY::add'],
        );
        $response = $responses[$jsApiKey]['KLEVU_CATEGORY::add']['cat__' . $attributeFixture->getAttributeCode()];

        $this->assertInstanceOf(expected: SyncResultInterface::class, actual: $response);
        $this->assertTrue(condition: $response->isSuccess());
        $this->assertSame(expected: 200, actual: $response->getCode());
        $this->assertContains(needle: 'Attribute saved successfully', haystack: $response->getMessages());
    }
}
