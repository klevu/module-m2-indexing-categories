<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Plugin\Catalog\Model\ResourceModel;

use Klevu\Indexing\Logger\Logger as LoggerVirtualType;
use Klevu\Indexing\Model\Update\Entity;
use Klevu\IndexingApi\Service\EntityUpdateResponderServiceInterface;
use Klevu\IndexingApi\Service\Provider\AttributesToWatchProviderInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResourceModel;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Model\AbstractModel;
use Psr\Log\LoggerInterface;

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
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var string[]
     */
    private array $attributesToTriggerUpdateOfDescendents = [];
    /**
     * @var string[]
     */
    private array $changedAttributes = [];

    /**
     * @param CategoryFactory $categoryFactory
     * @param EntityUpdateResponderServiceInterface $responderService
     * @param AttributesToWatchProviderInterface $attributesToWatchProvider
     * @param LoggerInterface|null $logger
     * @param string[] $attributesToTriggerUpdateOfDescendents
     */
    public function __construct(
        CategoryFactory $categoryFactory,
        EntityUpdateResponderServiceInterface $responderService,
        AttributesToWatchProviderInterface $attributesToWatchProvider,
        ?LoggerInterface $logger = null,
        array $attributesToTriggerUpdateOfDescendents = [],
    ) {
        $this->categoryFactory = $categoryFactory;
        $this->responderService = $responderService;
        $this->attributesToWatchProvider = $attributesToWatchProvider;
        $this->logger = $logger ?: ObjectManager::getInstance()->get(LoggerVirtualType::class); // @phpstan-ignore-line
        array_walk(
            $attributesToTriggerUpdateOfDescendents,
            [$this, 'setAttributeToTriggerUpdateOfDescendents'],
        );
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
                Entity::ENTITY_IDS => $this->getEntityIds($object),
                Entity::STORE_IDS => $this->getStoreIds($originalCategory, $object),
                EntityUpdateResponderServiceInterface::CHANGED_ATTRIBUTES => $this->changedAttributes,
            ];
            $this->responderService->execute($data);
        }

        return $return;
    }

    /**
     * @param string $attributeToTriggerUpdateOfDescendents
     *
     * @return void
     */
    private function setAttributeToTriggerUpdateOfDescendents(string $attributeToTriggerUpdateOfDescendents): void
    {
        $this->attributesToTriggerUpdateOfDescendents[] = $attributeToTriggerUpdateOfDescendents;
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
            if ($originalCategory->getData($attribute) !== $category->getData($attribute)) {
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

    /**
     * @param AbstractModel&CategoryInterface $category
     *
     * @return int[]
     */
    private function getEntityIds(CategoryInterface&AbstractModel $category): array
    {
        $descendentCategoryIds = $this->isUpdateOfDescendentCategoriesRequired()
            ? $this->getDescendentCategoryIds(category: $category)
            : [];

        return array_merge(
            [(int)$category->getId()],
            array_map(callback: 'intval', array: $descendentCategoryIds),
        );
    }

    /**
     * @return bool
     */
    private function isUpdateOfDescendentCategoriesRequired(): bool
    {
        $return = false;
        foreach ($this->attributesToTriggerUpdateOfDescendents as $attribute) {
            $return = $return || in_array(needle: $attribute, haystack: $this->changedAttributes, strict: true);
        }

        return $return;
    }

    /**
     * @param CategoryInterface $category
     * @param int[] $descendentCategoryIds
     *
     * @return int[]
     */
    private function getDescendentCategoryIds(CategoryInterface $category, array $descendentCategoryIds = []): array
    {
        if (!method_exists($category, 'getChildrenCategories')) {
            $this->logger->error(
                message: 'Method: {method}, Error: Method "getChildrenCategories" does not exists on {category_model}',
                context: [
                    'method' => __METHOD__,
                    'line' => __LINE__,
                    'category_model' => $category::class,
                ],
            );

            return $descendentCategoryIds;
        }
        $childCategories = $category->getChildrenCategories();
        foreach ($childCategories as $childCategory) {
            $descendentCategoryIds[] = $childCategory->getId();
            $descendentCategoryIds = $this->getDescendentCategoryIds(
                category: $childCategory,
                descendentCategoryIds: $descendentCategoryIds,
            );
        }

        return $descendentCategoryIds;
    }
}
