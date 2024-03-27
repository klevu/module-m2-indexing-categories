<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Setup\Patch\Data;

use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Service\Provider\DefaultIndexingAttributesAspectMappingProviderInterface;
use Klevu\IndexingCategories\Model\Source\Aspect;
use Magento\Catalog\Api\Data\CategoryAttributeInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

class SetDefaultAttributeAspectMapping implements DataPatchInterface
{
    /**
     * @var AttributeRepositoryInterface
     */
    private AttributeRepositoryInterface $attributeRepository;
    /**
     * @var DefaultIndexingAttributesAspectMappingProviderInterface
     */
    private DefaultIndexingAttributesAspectMappingProviderInterface $defaultMappingProvider;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param AttributeRepositoryInterface $attributeRepository
     * @param DefaultIndexingAttributesAspectMappingProviderInterface $defaultMappingProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        AttributeRepositoryInterface $attributeRepository,
        DefaultIndexingAttributesAspectMappingProviderInterface $defaultMappingProvider,
        LoggerInterface $logger,
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->defaultMappingProvider = $defaultMappingProvider;
        $this->logger = $logger;
    }

    /**
     * @return SetDefaultAttributeAspectMapping
     */
    public function apply(): self
    {
        $this->setDefaultAttributeAspects();

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
    private function setDefaultAttributeAspects(): void
    {
        /** @var array<string, Aspect> $defaultIndexableAttributes */
        $defaultIndexableAttributes = $this->defaultMappingProvider->get();
        foreach ($defaultIndexableAttributes as $attributeCode => $aspect) {
            try {
                $attribute = $this->attributeRepository->get(
                    entityTypeCode: CategoryAttributeInterface::ENTITY_TYPE_CODE,
                    attributeCode: $attributeCode,
                );
                $attribute->setData( //@phpstan-ignore-line
                    key: MagentoAttributeInterface::ATTRIBUTE_PROPERTY_ASPECT_MAPPING,
                    value: $aspect->value,
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
