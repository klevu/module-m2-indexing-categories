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
        $isIndexable = $indexAs !== IndexType::NO_INDEX->value;

        if (!$isIndexable) {
            $currentScope = $this->scopeProvider->getCurrentScope();
            $this->scopeProvider->setCurrentScope(scope: $store);
            $this->logger->debug(
                // phpcs:ignore Generic.Files.LineLength.TooLong
                message: 'Store ID: {storeId} Attribute ID: {attributeId} not indexable due to Klevu Index: {indexAs} in {method}',
                context: [
                    'storeId' => $store->getId(),
                    'attributeId' => $attribute->getAttributeId(),
                    'indexAs' => IndexType::NO_INDEX->label(),
                    'method' => __METHOD__,
                ],
            );
            if ($currentScope->getScopeObject()) {
                $this->scopeProvider->setCurrentScope(scope: $currentScope->getScopeObject());
            } else {
                $this->scopeProvider->unsetCurrentScope();
            }
        }

        return $isIndexable;
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
}
