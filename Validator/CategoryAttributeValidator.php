<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Validator;

use Klevu\IndexingApi\Validator\ValidatorInterface;
use Magento\Catalog\Api\Data\CategoryAttributeInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Type as EntityType;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Validator\AbstractValidator;
use Psr\Log\LoggerInterface;

class CategoryAttributeValidator extends AbstractValidator implements ValidatorInterface
{
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var EavConfig
     */
    private readonly EavConfig $eavConfig;

    /**
     * @param LoggerInterface $logger
     * @param EavConfig $eavConfig
     */
    public function __construct(
        LoggerInterface $logger,
        EavConfig $eavConfig,
    ) {
        $this->logger = $logger;
        $this->eavConfig = $eavConfig;
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public function isValid(mixed $value): bool
    {
        $this->_clearMessages();

        return $this->validateType($value)
            && $this->validateEntityType($value);
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    private function validateType(mixed $value): bool
    {
        if ($value instanceof AttributeInterface) {
            return true;
        }
        $this->_addMessages([
            __(
                'Invalid argument provided. Expected %1 or %2, received %3.',
                CategoryAttributeInterface::class,
                AttributeInterface::class,
                get_debug_type($value),
            )->render(),
        ]);

        return false;
    }

    /**
     * @param AttributeInterface $value
     *
     * @return bool
     */
    private function validateEntityType(AttributeInterface $value): bool
    {
        if ($value instanceof CategoryAttributeInterface) {
            return true;
        }
        $entityType = $this->getCategoryEntityType();
        if ($value->getEntityTypeId() === $entityType?->getEntityTypeId()) {
            return true;
        }
        $this->_addMessages([
            __(
                'Invalid argument provided. %1 must be of type %2.',
                AttributeInterface::class,
                CategoryAttributeInterface::ENTITY_TYPE_CODE,
            )->render(),
        ]);

        return false;
    }

    /**
     * @return EntityType|null
     */
    private function getCategoryEntityType(): ?EntityType
    {
        $return = null;
        try {
            $return = $this->eavConfig->getEntityType(
                code: CategoryAttributeInterface::ENTITY_TYPE_CODE,
            );
        } catch (LocalizedException $exception) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
        }

        return $return;
    }
}
