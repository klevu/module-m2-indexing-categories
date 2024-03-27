<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Service\Provider;

use Klevu\IndexingApi\Service\Provider\DefaultIndexingAttributesAspectMappingProviderInterface;
use Klevu\IndexingCategories\Model\Source\Aspect;
use Klevu\IndexingCategories\Service\Provider\DefaultIndexingAttributesAspectMappingProvider;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

// phpcs:disable Generic.Files.LineLength.TooLong
/**
 * @covers \Klevu\IndexingCategories\Service\Provider\DefaultIndexingAttributesAspectMappingProvider::class
 * @method DefaultIndexingAttributesAspectMappingProviderInterface instantiateTestObject(?array $arguments = null)
 * @method DefaultIndexingAttributesAspectMappingProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class DefaultIndexingAttributesAspectMappingProviderTest extends TestCase
{
    // phpcs:enable Generic.Files.LineLength.TooLong
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; //@phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = Bootstrap::getObjectManager();

        $this->implementationFqcn = DefaultIndexingAttributesAspectMappingProvider::class;
        $this->interfaceFqcn = DefaultIndexingAttributesAspectMappingProviderInterface::class;
    }

    public function testGet_ReturnsAspectMapping(): void
    {
        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertArrayHasKey(key: 'description', array: $result);
        $this->assertSame(expected: $result['description'], actual: Aspect::ALL);

        $this->assertArrayHasKey(key: 'is_active', array: $result);
        $this->assertSame(expected: $result['is_active'], actual: Aspect::ALL);

        $this->assertArrayHasKey(key: 'name', array: $result);
        $this->assertSame(expected: $result['name'], actual: Aspect::ALL);

        $this->assertArrayHasKey(key: 'parent_id', array: $result);
        $this->assertSame(expected: $result['parent_id'], actual: Aspect::ALL);

        $this->assertArrayHasKey(key: 'store_id', array: $result);
        $this->assertSame(expected: $result['store_id'], actual: Aspect::ALL);

        $this->assertArrayHasKey(key: 'url_key', array: $result);
        $this->assertSame(expected: $result['url_key'], actual: Aspect::ALL);
    }
}
