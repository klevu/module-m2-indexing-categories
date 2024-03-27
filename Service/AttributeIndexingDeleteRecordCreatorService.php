<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Service;

use Klevu\Indexing\Exception\AttributeMappingMissingException;
use Klevu\IndexingApi\Service\AttributeIndexingDeleteRecordCreatorServiceInterface;
use Klevu\IndexingApi\Service\Mapper\MagentoToKlevuAttributeMapperInterface;
use Klevu\PhpSDK\Api\Model\Indexing\AttributeInterface as SdkAttributeInterface;
use Klevu\PhpSDK\Model\Indexing\AttributeFactory;
use Klevu\PhpSDK\Model\Indexing\DataType;

class AttributeIndexingDeleteRecordCreatorService implements AttributeIndexingDeleteRecordCreatorServiceInterface
{
    /**
     * @var AttributeFactory
     */
    private readonly AttributeFactory $attributeFactory;
    /**
     * @var MagentoToKlevuAttributeMapperInterface
     */
    private readonly MagentoToKlevuAttributeMapperInterface $attributeMapperService;

    /**
     * @param AttributeFactory $attributeFactory
     * @param MagentoToKlevuAttributeMapperInterface $attributeMapperService
     */
    public function __construct(
        AttributeFactory $attributeFactory,
        MagentoToKlevuAttributeMapperInterface $attributeMapperService,
    ) {
        $this->attributeFactory = $attributeFactory;
        $this->attributeMapperService = $attributeMapperService;
    }

    /**
     * @param string $attributeCode
     *
     * @return SdkAttributeInterface
     * @throws AttributeMappingMissingException
     */
    public function execute(string $attributeCode): SdkAttributeInterface
    {
        return $this->attributeFactory->create(data: [
            'attributeName' => $this->getAttributeName($attributeCode),
            'datatype' => DataType::STRING->value, // required field, but delete doesn't use it so just set to string
        ]);
    }

    /**
     * @param string $attributeCode
     *
     * @return string
     * @throws AttributeMappingMissingException
     */
    private function getAttributeName(string $attributeCode): string
    {
        return $this->attributeMapperService->getByCode($attributeCode);
    }
}
