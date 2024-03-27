<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Service\Provider;

use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Service\Provider\AttributesToWatchProviderInterface;
use Klevu\IndexingCategories\Model\Source\Aspect;
use Magento\Catalog\Api\Data\CategoryAttributeInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\FilterBuilderFactory;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilderFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Api\SearchCriteriaInterface;

class AttributesToWatchProvider implements AttributesToWatchProviderInterface
{
    /**
     * @var AttributeRepositoryInterface
     */
    private readonly AttributeRepositoryInterface $attributeRepository;
    /**
     * @var SearchCriteriaBuilderFactory
     */
    private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory;
    /**
     * @var FilterBuilderFactory
     */
    private readonly FilterBuilderFactory $filterBuilderFactory;
    /**
     * @var FilterGroupBuilderFactory
     */
    private readonly FilterGroupBuilderFactory $filterGroupBuilderFactory;
    /**
     * Another way add attribute to watch for changes.
     * You could set this directly in the database (catalog_eav_attribute table, klevu_aspect_mapping column).
     * For products, this can be set in the admin, however there is no admin GUI for category attributes.
     * You can inject attributes via di.xml
     * e.g.
     *   <argument name="attributesToWatch" xsi:type="array">
     *    <item name="meta_description" xsi:type="const">Klevu\IndexingCategories\Model\Source\Aspect::ALL</item>
     *   </argument>
     *
     * @var array<string, Aspect>
     */
    private array $attributesToWatch = [];

    /**
     * @param AttributeRepositoryInterface $attributeRepository
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param FilterBuilderFactory $filterBuilderFactory
     * @param FilterGroupBuilderFactory $filterGroupBuilderFactory
     * @param string[] $attributesToWatch
     */
    public function __construct(
        AttributeRepositoryInterface $attributeRepository,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        FilterBuilderFactory $filterBuilderFactory,
        FilterGroupBuilderFactory $filterGroupBuilderFactory,
        array $attributesToWatch = [],
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->filterBuilderFactory = $filterBuilderFactory;
        $this->filterGroupBuilderFactory = $filterGroupBuilderFactory;
        array_walk($attributesToWatch, [$this, 'setAttributesToWatch']);
    }

    /**
     * @return string[]
     */
    public function getAttributeCodes(): array
    {
        $attributeCodes = array_map(
            callback: static fn (AttributeInterface $attribute): string => ($attribute->getAttributeCode()),
            array: $this->getAttributes(),
        );

        return array_values(
            array_unique(
                array_merge(
                    $attributeCodes,
                    array_keys($this->attributesToWatch),
                ),
            ),
        );
    }

    /**
     * @return array<string, Aspect>
     */
    public function getAspectMapping(): array
    {
        $attributeMapping = [];
        foreach ($this->getAttributes() as $attribute) {
            // @phpstan-ignore-next-line
            $aspect = $attribute->getData(MagentoAttributeInterface::ATTRIBUTE_PROPERTY_ASPECT_MAPPING);
            $attributeMapping[$attribute->getAttributeCode()] = Aspect::from((int)$aspect);
        }

        return array_merge(
            $attributeMapping,
            $this->attributesToWatch,
        );
    }

    /**
     * @param Aspect $aspect
     * @param string $attributeCode
     *
     * @return void
     */
    private function setAttributesToWatch(Aspect $aspect, string $attributeCode): void
    {
        $this->attributesToWatch[$attributeCode] = $aspect;
    }

    /**
     * @return AttributeInterface[]
     */
    private function getAttributes(): array
    {
        $searchResults = $this->attributeRepository->getList(
            entityTypeCode: CategoryAttributeInterface::ENTITY_TYPE_CODE,
            searchCriteria: $this->getSearchCriteria(),
        );

        return $searchResults->getItems();
    }

    /**
     * @return SearchCriteriaInterface
     */
    private function getSearchCriteria(): SearchCriteriaInterface
    {
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        /** @var FilterBuilder $filterBuilder */
        $filterBuilder = $this->filterBuilderFactory->create();
        /** @var FilterGroupBuilder $filterGroupBuilder */
        $filterGroupBuilder = $this->filterGroupBuilderFactory->create();
        $filterGroupBuilder->addFilter(
            filter: $this->getFilterForIndexableCustomAttributes(filterBuilder: $filterBuilder),
        );
        /** @var FilterGroup $filterGroup */
        $filterGroup = $filterGroupBuilder->create();
        $searchCriteriaBuilder->setFilterGroups(filterGroups: [$filterGroup]);

        return $searchCriteriaBuilder->create();
    }

    /**
     * @param FilterBuilder $filterBuilder
     *
     * @return Filter
     */
    private function getFilterForIndexableCustomAttributes(FilterBuilder $filterBuilder): Filter
    {
        $filterBuilder->setField(field: MagentoAttributeInterface::ATTRIBUTE_PROPERTY_ASPECT_MAPPING);
        $filterBuilder->setValue(value: (string)Aspect::NONE->value);
        $filterBuilder->setConditionType(conditionType: 'neq');

        return $filterBuilder->create();
    }
}
