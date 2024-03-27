<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Plugin\Catalog\Model\ResourceModel;

use Klevu\Indexing\Model\Update\Entity;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Klevu\IndexingApi\Service\Provider\AttributesToWatchProviderInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResourceModel;
use Magento\Framework\Model\AbstractModel;

class CategoryPlugin
{
    /**
     * @var CategoryFactory
     */
    private readonly CategoryFactory $categoryFactory;
    /**
     * @var EntityUpdateResponderServiceInterface
     */
    private readonly EntityUpdateResponderServiceInterface $responderService;
    /**
     * @var AttributesToWatchProviderInterface
     */
    private readonly AttributesToWatchProviderInterface $attributesToWatchProvider;
    /**
     * @var string[]
     */
    private array $changedAttributes = [];

    /**
     * @param CategoryFactory $categoryFactory
     * @param EntityUpdateResponderServiceInterface $responderService
     * @param AttributesToWatchProviderInterface $attributesToWatchProvider
     */
    public function __construct(
        CategoryFactory $categoryFactory,
        EntityUpdateResponderServiceInterface $responderService,
        AttributesToWatchProviderInterface $attributesToWatchProvider,
    ) {
        $this->categoryFactory = $categoryFactory;
        $this->responderService = $responderService;
        $this->attributesToWatchProvider = $attributesToWatchProvider;
    }

    public function aroundSave(
        CategoryResourceModel $resourceModel,
        \Closure $proceed,
        AbstractModel $object,
    ): CategoryResourceModel {
        /** @var CategoryInterface&AbstractModel $object */
        $originalCategory = $this->getOriginalCategory($resourceModel, $object);

        $return = $proceed($object);

        if ($this->isUpdateRequired($originalCategory, $object)) {
            $data = [
                Entity::ENTITY_IDS => [(int)$object->getId()],
                Entity::STORE_IDS => $this->getStoreIds($originalCategory, $object),
                EntityUpdateResponderServiceInterface::CHANGED_ATTRIBUTES => $this->changedAttributes,
            ];
            $this->responderService->execute($data);
        }

        return $return;
    }

    /**
     * @param mixed $originalCategory
     * @param CategoryInterface&AbstractModel $category
     *
     * @return bool
     */
    private function isUpdateRequired(mixed $originalCategory, CategoryInterface&AbstractModel $category): bool
    {
        if (!$originalCategory->getId()) {
            // is a new category
            return true;
        }
        foreach ($this->attributesToWatchProvider->getAttributeCodes() as $attribute) {
            if ($originalCategory->getData($attribute) === $category->getData($attribute)) {
                $this->changedAttributes[] = $attribute;
            }
        }

        return (bool)count($this->changedAttributes);
    }

    /**
     * @param CategoryResourceModel $resourceModel
     * @param AbstractModel&CategoryInterface $category
     *
     * @return AbstractModel&CategoryInterface
     */
    private function getOriginalCategory(
        CategoryResourceModel $resourceModel,
        CategoryInterface&AbstractModel $category,
    ): AbstractModel&CategoryInterface {
        $originalCategory = $this->categoryFactory->create();
        $categoryId = $category->getId();
        if ($categoryId) {
            $resourceModel->load($originalCategory, $categoryId);
        }

        return $originalCategory;
    }

    /**
     * @param AbstractModel&CategoryInterface $originalCategory
     * @param AbstractModel&CategoryInterface $category
     *
     * @return int[]
     */
    private function getStoreIds(
        AbstractModel&CategoryInterface $originalCategory,
        AbstractModel&CategoryInterface $category,
    ): array {

        return array_filter(
            array_unique(
                array_merge(
                    [(int)$originalCategory->getStoreId()],
                    [(int)$category->getStoreId()],
                ),
            ),
        );
    }

}
