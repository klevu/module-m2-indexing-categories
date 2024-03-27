<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Validator;

use Klevu\IndexingApi\Validator\ValidatorInterface;
use Klevu\IndexingCategories\Validator\CategoryAttributeValidator;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\CategoryAttributeInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Eav\Model\Config;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Klevu\IndexingCategories\Validator\CategoryAttributeValidator::class
 * @method ValidatorInterface instantiateTestObject(?array $arguments = null)
 * @method ValidatorInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class CategoryAttributeValidatorTest extends TestCase
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

        $this->implementationFqcn = CategoryAttributeValidator::class;
        $this->interfaceFqcn = ValidatorInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @dataProvider dataProvider_testIsValid_ReturnsFalse_ForIncorrectType
     */
    public function testIsValid_ReturnsFalse_ForIncorrectType(mixed $invalidType): void
    {
        $validator = $this->instantiateTestObject();
        $isValid = $validator->isValid(value: $invalidType);
        $hasMessages = $validator->hasMessages();
        $messages = $validator->getMessages();

        $this->assertFalse(condition: $isValid);
        $this->assertTrue(condition: $hasMessages);
        $this->assertContains(
            needle: sprintf(
                'Invalid argument provided. Expected %s or %s, received %s.',
                CategoryAttributeInterface::class,
                AttributeInterface::class,
                get_debug_type($invalidType),
            ),
            haystack: $messages,
        );
    }

    /**
     * @return mixed[][]
     */
    public function dataProvider_testIsValid_ReturnsFalse_ForIncorrectType(): array
    {
        return [
            [0],
            [1.23],
            ['string'],
            [true],
            [false],
            [null],
            [new DataObject()],
        ];
    }

    public function testIsValid_ReturnsFalse_ForProductAttributes(): void
    {
        $productAttribute = $this->objectManager->get(ProductAttributeInterface::class);

        $validator = $this->instantiateTestObject();
        $isValid = $validator->isValid(value: $productAttribute);
        $hasMessages = $validator->hasMessages();
        $messages = $validator->getMessages();

        $this->assertFalse(condition: $isValid);
        $this->assertTrue(condition: $hasMessages);
        $this->assertContains(
            needle: sprintf(
                'Invalid argument provided. %s must be of type %s.',
                AttributeInterface::class,
                CategoryAttributeInterface::ENTITY_TYPE_CODE,
            ),
            haystack: $messages,
        );
    }

    public function testIsValid_ReturnsTrue_ForCategoryAttributes(): void
    {
        $categoryAttribute = $this->objectManager->get(CategoryAttributeInterface::class);

        $validator = $this->instantiateTestObject();
        $isValid = $validator->isValid(value: $categoryAttribute);
        $hasMessages = $validator->hasMessages();
        $messages = $validator->getMessages();

        $this->assertTrue(condition: $isValid);
        $this->assertFalse(condition: $hasMessages);
        $this->assertEmpty($messages);
    }

    public function testIsValid_ReturnsTrue_ForAttributesTypeCategory(): void
    {
        $categoryAttribute = $this->objectManager->get(AttributeInterface::class);
        $categoryAttribute->setEntityTypeId(
            $this->getCategoryEntityType(),
        );

        $validator = $this->instantiateTestObject();
        $isValid = $validator->isValid(value: $categoryAttribute);
        $hasMessages = $validator->hasMessages();
        $messages = $validator->getMessages();

        $this->assertTrue(condition: $isValid);
        $this->assertFalse(condition: $hasMessages);
        $this->assertEmpty($messages);
    }

    public function testIsValid_LogsError_WhenExceptionThrown_ByGetEntityType(): void
    {
        $exceptionMessage = 'Invalid entity_type specified: invalid_type';

        $mockEavConfig = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockEavConfig->expects($this->once())
            ->method('getEntityType')
            ->willThrowException(new LocalizedException(__($exceptionMessage)));

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    'method' => 'Klevu\IndexingCategories\Validator\CategoryAttributeValidator::getCategoryEntityType',
                    'message' => $exceptionMessage,
                ],
            );

        $categoryAttribute = $this->objectManager->get(AttributeInterface::class);
        $categoryAttribute->setEntityTypeId(
            $this->getCategoryEntityType(),
        );

        $validator = $this->instantiateTestObject([
            'logger' => $mockLogger,
            'eavConfig' => $mockEavConfig,
        ]);
        $validator->isValid(value: $categoryAttribute);
    }

    /**
     * @return string|null
     * @throws LocalizedException
     */
    private function getCategoryEntityType(): ?string
    {
        $eavConfig = $this->objectManager->get(Config::class);
        $entityType = $eavConfig->getEntityType(
            code: CategoryAttributeInterface::ENTITY_TYPE_CODE,
        );

        return $entityType->getEntityTypeId();
    }
}
