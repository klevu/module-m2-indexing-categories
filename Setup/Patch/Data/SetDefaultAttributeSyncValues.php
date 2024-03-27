<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Setup\Patch\Data;

use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\Provider\DefaultIndexingAttributesProviderInterface;
use Magento\Catalog\Api\Data\CategoryAttributeInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

class SetDefaultAttributeSyncValues implements DataPatchInterface
{
    /**
     * @var AttributeRepositoryInterface
     */
    private AttributeRepositoryInterface $attributeRepository;
    /**
     * @var DefaultIndexingAttributesProviderInterface
     */
    private DefaultIndexingAttributesProviderInterface $defaultIndexingAttributesProvider;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param AttributeRepositoryInterface $attributeRepository
     * @param DefaultIndexingAttributesProviderInterface $defaultIndexingAttributesProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        AttributeRepositoryInterface $attributeRepository,
        DefaultIndexingAttributesProviderInterface $defaultIndexingAttributesProvider,
        LoggerInterface $logger,
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->defaultIndexingAttributesProvider = $defaultIndexingAttributesProvider;
        $this->logger = $logger;
    }

    /**
     * @return SetDefaultAttributeSyncValues
     */
    public function apply(): self
    {
        $this->setDefaultAttributeToBeIndexable();

        return $this;
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return void
     */
    private function setDefaultAttributeToBeIndexable(): void
    {
        /** @var array<string, IndexType> $defaultIndexableAttributes */
        $defaultIndexableAttributes = $this->defaultIndexingAttributesProvider->get();
        foreach ($defaultIndexableAttributes as $attributeCode => $indexType) {
            try {
                $attribute = $this->attributeRepository->get(
                    entityTypeCode: CategoryAttributeInterface::ENTITY_TYPE_CODE,
                    attributeCode: $attributeCode,
                );
                $attribute->setData( //@phpstan-ignore-line
                    key: MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE,
                    value: $indexType->value,
                );
                $this->attributeRepository->save(
                    attribute: $attribute,
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
        }
    }
}
