<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Service\Determiner;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Exception\InvalidAttributeException;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\Determiner\IsAttributeIndexableDeterminerInterface;
use Klevu\IndexingApi\Validator\ValidatorInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class AttributeIsIndexableDeterminer implements IsAttributeIndexableDeterminerInterface
{
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var ValidatorInterface
     */
    private readonly ValidatorInterface $categoryAttributeValidator;
    /**
     * @var ScopeProviderInterface
     */
    private readonly ScopeProviderInterface $scopeProvider;

    /**
     * @param LoggerInterface $logger
     * @param ValidatorInterface $categoryAttributeValidator
     * @param ScopeProviderInterface $scopeProvider
     */
    public function __construct(
        LoggerInterface $logger,
        ValidatorInterface $categoryAttributeValidator,
        ScopeProviderInterface $scopeProvider,
    ) {
        $this->logger = $logger;
        $this->categoryAttributeValidator = $categoryAttributeValidator;
        $this->scopeProvider = $scopeProvider;
    }

    /**
     * @param AttributeInterface $attribute
     * @param StoreInterface $store
     *
     * @return bool
     * @throws InvalidAttributeException
     */
    public function execute(AttributeInterface $attribute, StoreInterface $store): bool
    {
        $this->validateAttribute(attribute: $attribute);

        return $this->isIndexable(attribute: $attribute, store: $store);
    }

    /**
     * @param AttributeInterface $attribute
     * @param StoreInterface $store
     *
     * @return bool
     */
    private function isIndexable(AttributeInterface $attribute, StoreInterface $store): bool
    {
        $indexAs = (int)$attribute->getData( //@phpstan-ignore-line
            key: MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE,
        );
        try {
            $indexType = IndexType::from($indexAs);
        } catch (\ValueError) {
            $this->logWithScope(
                store: $store,
                level: LogLevel::WARNING,
                message: 'Store ID: {storeId} Attribute ID: {attributeId} has an invalid {attributeProperty} value',
                context: [
                    'storeId' => $store->getId(),
                    'attributeId' => $attribute->getAttributeId(),
                    'attributeProperty' => MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE,
                    'indexAs' => $indexAs,
                    'method' => __METHOD__,
                ],
            );
            
            return false;
        }

        if (!$indexType->isIndexable()) {
            $this->logWithScope(
                store: $store,
                level: LogLevel::DEBUG,
                // phpcs:ignore Generic.Files.LineLength.TooLong
                message: 'Store ID: {storeId} Attribute ID: {attributeId} not indexable due to Klevu Index: {indexAs} in {method}',
                context: [
                    'storeId' => $store->getId(),
                    'attributeId' => $attribute->getAttributeId(),
                    'indexAs' => IndexType::NO_INDEX->label(),
                    'method' => __METHOD__,
                ],
            );
        }

        return $indexType->isIndexable();
    }

    /**
     * @param AttributeInterface $attribute
     *
     * @return void
     * @throws InvalidAttributeException
     */
    private function validateAttribute(AttributeInterface $attribute): void
    {
        $isValid = $this->categoryAttributeValidator->isValid(value: $attribute);
        if (!$isValid) {
            $messages = [];
            if ($this->categoryAttributeValidator->hasMessages()) {
                $messages = $this->categoryAttributeValidator->getMessages();
            }
            throw new \InvalidArgumentException(
                sprintf('Invalid Attribute: %s', implode(': ', $messages)),
            );
        }
    }

    /**
     * @param StoreInterface $store
     * @param string $level
     * @param string $message
     * @param mixed[] $context
     *
     * @return void
     */
    private function logWithScope(
        StoreInterface $store,
        string $level,
        string $message,
        array $context = [],
    ): void {
        $currentScope = $this->scopeProvider->getCurrentScope();
        $this->scopeProvider->setCurrentScope(scope: $store);
        $this->logger->log(
            level: $level,
            message: $message,
            context: $context,
        );
        if ($currentScope->getScopeObject()) {
            $this->scopeProvider->setCurrentScope(scope: $currentScope->getScopeObject());
        } else {
            $this->scopeProvider->unsetCurrentScope();
        }
    }
}
