<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Setup\Patch\Data;

use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\Provider\DefaultIndexingAttributesProviderInterface;
use Klevu\IndexingCategories\Setup\Patch\Data\SetDefaultAttributeSyncValues;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Model\Attribute;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Klevu\IndexingCategories\Setup\Patch\Data\SetDefaultAttributeSyncValues::class
 * @method SetDefaultAttributeSyncValues instantiateTestObject(?array $arguments = null)
 * @method SetDefaultAttributeSyncValues instantiateTestObjectFromInterface(?array $arguments = null)
 */
class SetDefaultAttributeSyncValuesTest extends TestCase
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

        $this->implementationFqcn = SetDefaultAttributeSyncValues::class;
        $this->interfaceFqcn = DataPatchInterface::class;
    }

    public function testGetAliases_ReturnsEmptyArray(): void
    {
        $patch = $this->instantiateTestObject();
        $aliases = $patch->getAliases();

        $this->assertCount(expectedCount: 0, haystack: $aliases);
    }

    public function testGetDependencies_ReturnsEmptyArray(): void
    {
        $patch = $this->instantiateTestObject();
        $dependencies = $patch->getDependencies();

        $this->assertCount(expectedCount: 0, haystack: $dependencies);
    }

    public function testApply_LogsError_WhenNoSuchEntityExceptionThrown(): void
    {
        $exceptionMessage = 'Exception thrown by AttributeRepositoryInterface::get()';

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    //phpcs:ignore Generic.Files.LineLength.TooLong
                    'method' => 'Klevu\IndexingCategories\Setup\Patch\Data\SetDefaultAttributeSyncValues::setDefaultAttributeToBeIndexable',
                    'message' => $exceptionMessage,
                ],
            );

        $mockAttributeRepository = $this->getMockBuilder(AttributeRepositoryInterface::class)
            ->getMock();
        $mockAttributeRepository->expects($this->once())
            ->method('get')
            ->willThrowException(
                new NoSuchEntityException(__($exceptionMessage)),
            );
        $mockAttributeRepository->expects($this->never())
            ->method('save');

        //phpcs:ignore Generic.Files.LineLength.TooLong
        $mockDefaultIndexingAttributesProvider = $this->getMockBuilder(DefaultIndexingAttributesProviderInterface::class)
            ->getMock();
        $mockDefaultIndexingAttributesProvider->expects($this->once())
            ->method('get')
            ->willReturn([
                'klevu_test_attribute' => IndexType::INDEX,
            ]);

        // is thrown in get
        $patch = $this->instantiateTestObject([
            'attributeRepository' => $mockAttributeRepository,
            'defaultIndexingAttributesProvider' => $mockDefaultIndexingAttributesProvider,
            'logger' => $mockLogger,
        ]);
        $patch->apply();
    }

    public function testApply_LogsError_WhenStateExceptionThrown(): void
    {
        $exceptionMessage = 'Exception thrown by AttributeRepositoryInterface::save()';

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    //phpcs:ignore Generic.Files.LineLength.TooLong
                    'method' => 'Klevu\IndexingCategories\Setup\Patch\Data\SetDefaultAttributeSyncValues::setDefaultAttributeToBeIndexable',
                    'message' => $exceptionMessage,
                ],
            );

        $mockAttribute = $this->getMockBuilder(Attribute::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAttribute->expects($this->once())
            ->method('setData')
            ->with(
                MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE,
                IndexType::INDEX->value,
            );

        $mockAttributeRepository = $this->getMockBuilder(AttributeRepositoryInterface::class)
            ->getMock();
        $mockAttributeRepository->expects($this->once())
            ->method('get')
            ->willReturn($mockAttribute);
        $mockAttributeRepository->expects($this->once())
            ->method('save')
            ->willThrowException(
                new StateException(__($exceptionMessage)),
            );

        //phpcs:ignore Generic.Files.LineLength.TooLong
        $mockDefaultIndexingAttributesProvider = $this->getMockBuilder(DefaultIndexingAttributesProviderInterface::class)
            ->getMock();
        $mockDefaultIndexingAttributesProvider->expects($this->once())
            ->method('get')
            ->willReturn([
                'klevu_test_attribute' => IndexType::INDEX,
            ]);

        $patch = $this->instantiateTestObject([
            'attributeRepository' => $mockAttributeRepository,
            'defaultIndexingAttributesProvider' => $mockDefaultIndexingAttributesProvider,
            'logger' => $mockLogger,
        ]);
        $patch->apply();
    }
}
