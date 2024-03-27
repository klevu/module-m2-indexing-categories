<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Service\Provider\Discovery;

use Klevu\IndexingApi\Service\Provider\Discovery\AttributeCollectionInterface;
use Magento\Catalog\Model\ResourceModel\Category\Attribute\Collection as AttributeCollection;
use Magento\Catalog\Model\ResourceModel\Category\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Eav\Api\Data\AttributeInterface;

class CategoryAttributeCollection implements AttributeCollectionInterface
{
    /**
     * @var AttributeCollectionFactory
     */
    private readonly AttributeCollectionFactory $attributeCollectionFactory;

    /**
     * @param AttributeCollectionFactory $attributeCollectionFactory
     */
    public function __construct(
        AttributeCollectionFactory $attributeCollectionFactory,
    ) {
        $this->attributeCollectionFactory = $attributeCollectionFactory;
    }

    /**
     * @param int[]|null $attributeIds
     *
     * @return AttributeCollection
     */
    public function get(?array $attributeIds = []): AttributeCollection
    {
        $collection = $this->attributeCollectionFactory->create();
        if ($attributeIds) {
            $collection->addFieldToFilter(
                'main_table.' . AttributeInterface::ATTRIBUTE_ID,
                ['in' => implode(',', $attributeIds)],
            );
        }

        return $collection;
    }
}
