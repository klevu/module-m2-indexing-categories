<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Service\Provider;

use Klevu\Indexing\Exception\InvalidStaticAttributeConfigurationException;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\Provider\StaticAttributeProviderInterface;
use Klevu\IndexingApi\Validator\ValidatorInterface;
use Magento\Catalog\Api\Data\CategoryAttributeInterface;
use Magento\Catalog\Api\Data\CategoryAttributeInterfaceFactory;
use Magento\Catalog\Api\Data\EavAttributeInterface;
use Magento\Eav\Api\Data\AttributeFrontendLabelInterface;
use Magento\Eav\Api\Data\AttributeFrontendLabelInterfaceFactory;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\App\State as AppState;
use Magento\Framework\DataObject;
use Psr\Log\LoggerInterface;

class StaticAttributeProvider implements StaticAttributeProviderInterface
{
    /**
     * @var CategoryAttributeInterfaceFactory
     */
    private readonly CategoryAttributeInterfaceFactory $attributeFactory;
    /**
     * @var AttributeFrontendLabelInterfaceFactory
     */
    private readonly AttributeFrontendLabelInterfaceFactory $attributeFrontendLabelFactory;
    /**
     * @var AppState
     */
    private readonly AppState $appState;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var ValidatorInterface
     */
    private readonly ValidatorInterface $validator;
    /**
     * @var mixed[]
     */
    private array $attributes;

    /**
     * @param CategoryAttributeInterfaceFactory $attributeFactory
     * @param AttributeFrontendLabelInterfaceFactory $attributeFrontendLabelFactory
     * @param AppState $appState
     * @param LoggerInterface $logger
     * @param ValidatorInterface $validator
     * @param mixed[] $attributes
     */
    public function __construct(
        CategoryAttributeInterfaceFactory $attributeFactory,
        AttributeFrontendLabelInterfaceFactory $attributeFrontendLabelFactory,
        AppState $appState,
        LoggerInterface $logger,
        ValidatorInterface $validator,
        array $attributes = [],
    ) {
        $this->attributeFactory = $attributeFactory;
        $this->attributeFrontendLabelFactory = $attributeFrontendLabelFactory;
        $this->appState = $appState;
        $this->logger = $logger;
        $this->validator = $validator;
        $this->attributes = $attributes;
    }

    /**
     * @param int[]|null $attributeIds
     *
     * @return \Generator
     * @throws InvalidStaticAttributeConfigurationException
     */
    public function get(?array $attributeIds = []): \Generator
    {
        foreach ($this->attributes as $attribute) {
            $staticAttribute = $this->createAttribute($attribute);
            if ($attributeIds && !in_array($staticAttribute?->getAttributeId(), $attributeIds, true)) {
                continue;
            }
            if ($staticAttribute) {
                yield $staticAttribute;
            }
        }
    }

    /**
     * @param string $attributeCode
     *
     * @return EavAttributeInterface|null
     * @throws InvalidStaticAttributeConfigurationException
     */
    public function getByAttributeCode(string $attributeCode): ?EavAttributeInterface
    {
        $attributes = array_filter(
            array: iterator_to_array(iterator: $this->get(), preserve_keys: false),
            callback: static fn (AttributeInterface $attribute): bool => (
                $attribute->getAttributeCode() === $attributeCode
            ),
        );

        return $attributes ? array_shift($attributes) : null;
    }

    /**
     * @param mixed[] $data
     *
     * @return CategoryAttributeInterface|null
     * @throws InvalidStaticAttributeConfigurationException
     */
    private function createAttribute(array $data): ?CategoryAttributeInterface
    {
        if (!($this->validateAttributeData($data))) {
            return null;
        }
        /** @var DataObject|CategoryAttributeInterface $attribute */
        $attribute = $this->attributeFactory->create();
        $attribute->setAttributeId((int)$data['attribute_id']);
        $attribute->setAttributeCode($data['attribute_code']);
        $attribute->setData(
            key: MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE,
            value: IndexType::INDEX->value,
        );
        $attribute->setData(
            key: 'is_searchable',
            value: $data['is_searchable'] ?? false,
        );
        $attribute->setData(
            key: 'is_filterable',
            value: $data['is_filterable'] ?? false,
        );
        $attribute->setData(
            key: 'used_in_product_listing',
            value: $data['is_returnable'] ?? false,
        );
        $attribute->setDefaultFrontendLabel(
            $data['default_label'] ?? ucfirst(str_replace('_', ' ', $data['attribute_code'])),
        );
        if (($data['labels'] ?? null)) {
            $labels = [];
            foreach ($data['labels'] as $storeId => $labelName) {
                /** @var AttributeFrontendLabelInterface $label */
                $label = $this->attributeFrontendLabelFactory->create();
                $label->setLabel($labelName);
                $label->setStoreId((int)$storeId);
                $labels[] = $label;
            }
            $attribute = $attribute->setFrontendLabels($labels);
        }
        if (($data['is_global'] ?? null)) {
            $attribute->setData(key: 'is_global', value: $data['frontend_input']);
        }
        if (($data['is_html_allowed_on_front'] ?? null)) {
            $attribute->setIsHtmlAllowedOnFront(isHtmlAllowedOnFront: $data['is_html_allowed_on_front']);
        }
        if (($data['frontend_input'] ?? null)) {
            $attribute->setFrontendInput(frontendInput: $data['frontend_input']);
        }
        if (($data['backend_type'] ?? null)) {
            $attribute->setBackendType($data['backend_type']);
        }
        if (($data['source_model'] ?? null)) {
            $attribute->setSourceModel(sourceModel: $data['source_model']);
        }

        return $attribute;
    }

    /**
     * @param mixed[] $data
     *
     * @return bool
     * @throws InvalidStaticAttributeConfigurationException
     */
    private function validateAttributeData(array $data): bool
    {
        $isValid = $this->validator->isValid($data);
        if (!$isValid) {
            $messages = $this->validator->hasMessages()
                ? $this->validator->getMessages()
                : [];
            if ($this->appState->getMode() !== AppState::MODE_PRODUCTION) {
                throw new InvalidStaticAttributeConfigurationException(
                    __(
                        'Invalid Static Attribute data: %1',
                        implode('; ', $messages),
                    ),
                );
            }
            $this->logger->error(
                message: 'Method: {method}, Error: {error}',
                context: [
                    'method' => __METHOD__,
                    'error' => implode('; ', $messages),
                ],
            );
            return false;
        }
        return true;
    }
}
