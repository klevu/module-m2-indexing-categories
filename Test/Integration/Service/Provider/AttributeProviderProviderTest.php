<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Service\Provider;

use Klevu\Indexing\Service\Provider\AttributeProvider;
use Klevu\IndexingApi\Service\Provider\AttributeProviderProviderInterface;
use Klevu\IndexingCategories\Service\Provider\AttributeProviderProvider;
use Klevu\IndexingCategories\Service\Provider\Discovery\CategoryAttributeCollection;
use Klevu\IndexingProducts\Service\Provider\Discovery\ProductAttributeCollection;
use Klevu\IndexingProducts\Service\Provider\StaticAttributeProvider;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers AttributeProviderProvider
 * @method AttributeProviderProviderInterface instantiateTestObject(?array $arguments = null)
 * @method AttributeProviderProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AttributeProviderProviderTest extends TestCase
{
    use ObjectInstantiationTrait;
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

        $this->implementationFqcn = AttributeProviderProvider::class;
        $this->interfaceFqcn = AttributeProviderProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testGet_ReturnsEmptyArray_WhenNoProvidersProvided(): void
    {
        $provider = $this->instantiateTestObject([
            'attributeProviders' => [],
            'staticAttributeProviders' => [],
        ]);
        $result = $provider->get();

        $this->assertCount(expectedCount: 0, haystack: $result);
    }

    public function testGet_ReturnsArrayOfProviders(): void
    {
        $provider = $this->instantiateTestObject([
            'attributeProviders' => [
                $this->objectManager->create(
                    AttributeProvider::class,
                    [
                        'attributeCollection' => $this->objectManager->get(CategoryAttributeCollection::class),
                    ],
                ),
            ],
            'staticAttributeProviders' => [],
        ]);
        $result = $provider->get();

        $this->assertCount(expectedCount: 1, haystack: $result);
    }

    public function testGetStaticProviders_ReturnsEmptyArray_WhenNoStaticProvidersProvided(): void
    {
        $provider = $this->instantiateTestObject([
            'attributeProviders' => [
                $this->objectManager->create(
                    AttributeProvider::class,
                    [
                        'attributeCollection' => $this->objectManager->get(ProductAttributeCollection::class),
                    ],
                ),
            ],
            'staticAttributeProviders' => [],
        ]);
        $result = $provider->getStaticProviders();

        $this->assertCount(expectedCount: 0, haystack: $result);
    }

    public function testGetStaticProviders_ReturnsArrayOfProviders(): void
    {
        $provider = $this->instantiateTestObject([
            'attributeProviders' => [],
            'staticAttributeProviders' => [
                $this->objectManager->create(
                    StaticAttributeProvider::class,
                    [
                        'attributeCollection' => $this->objectManager->get(ProductAttributeCollection::class),
                    ],
                ),
            ],
        ]);
        $result = $provider->get();

        $this->assertCount(expectedCount: 1, haystack: $result);
    }
}
