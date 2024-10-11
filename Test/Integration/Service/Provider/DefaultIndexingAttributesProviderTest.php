<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Service\Provider;

use Klevu\Indexing\Service\Provider\DefaultIndexingAttributesProvider;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\Provider\DefaultIndexingAttributesProviderInterface;
use Klevu\IndexingApi\Service\Provider\StandardAttributesProviderInterface;
use Klevu\IndexingCategories\Service\Provider\DefaultIndexingAttributesProvider as DefaultIndexingAttributesProviderVirtualType; // phpcs:ignore Generic.Files.LineLength.TooLong
use Klevu\PhpSDK\Exception\Api\BadResponseException;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Indexing\Service\Provider\DefaultIndexingAttributesProvider::class
 * @method DefaultIndexingAttributesProvider instantiateTestObject(?array $arguments = null)
 * @method DefaultIndexingAttributesProvider instantiateTestObjectFromInterface(?array $arguments = null)
 */
class DefaultIndexingAttributesProviderTest extends TestCase
{
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

        $this->implementationFqcn = DefaultIndexingAttributesProviderVirtualType::class;
        $this->interfaceFqcn = DefaultIndexingAttributesProviderInterface::class;
        $this->implementationForVirtualType = DefaultIndexingAttributesProvider::class;
    }

    public function testGet_ReturnsAttributeList(): void
    {
        $provider = $this->instantiateTestObject();
        $attributes = $provider->get();

        $this->assertArrayHasKey(key: CategoryInterface::KEY_NAME, array: $attributes);
        $this->assertSame(expected: IndexType::INDEX, actual: $attributes[CategoryInterface::KEY_NAME]);

        $this->assertArrayHasKey(key: 'description', array: $attributes);
        $this->assertSame(expected: IndexType::INDEX, actual: $attributes['description']);

        $this->assertArrayHasKey(key: CategoryInterface::KEY_PATH, array: $attributes);
        $this->assertSame(expected: IndexType::INDEX, actual: $attributes[CategoryInterface::KEY_PATH]);

        $this->assertArrayHasKey(key: 'url_key', array: $attributes);
        $this->assertSame(expected: IndexType::INDEX, actual: $attributes['url_key']);
    }

    public function testGet_HandlesApiExceptionInterface_ReturnsStandardAttributeList(): void
    {
        $mockStandardAttributesProvider = $this->getMockBuilder(StandardAttributesProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockStandardAttributesProvider->expects($this->once())
            ->method('getAttributeCodesForAllApiKeys')
            ->willThrowException(
                new BadResponseException(
                    message: 'Bad Response',
                    code: 502,
                ),
            );

        $provider = $this->instantiateTestObject([
            'standardAttributesProvider' => $mockStandardAttributesProvider,
        ]);
        $attributes = $provider->get();

        $this->assertArrayHasKey(key: CategoryInterface::KEY_NAME, array: $attributes);
        $this->assertSame(expected: IndexType::INDEX, actual: $attributes[CategoryInterface::KEY_NAME]);

        $this->assertArrayHasKey(key: 'description', array: $attributes);
        $this->assertSame(expected: IndexType::INDEX, actual: $attributes['description']);

        $this->assertArrayHasKey(key: CategoryInterface::KEY_PATH, array: $attributes);
        $this->assertSame(expected: IndexType::INDEX, actual: $attributes[CategoryInterface::KEY_PATH]);

        $this->assertArrayHasKey(key: 'url_key', array: $attributes);
        $this->assertSame(expected: IndexType::INDEX, actual: $attributes['url_key']);
    }
}
