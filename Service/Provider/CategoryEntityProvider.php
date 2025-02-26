<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Service\Provider;

use Klevu\Configuration\Service\Provider\ScopeConfigProviderInterface;
use Klevu\Indexing\Validator\BatchSizeValidator;
use Klevu\IndexingApi\Service\Provider\EntityProviderInterface;
use Klevu\IndexingApi\Validator\ValidatorInterface;
use Klevu\IndexingCategories\Model\ResourceModel\Category\Collection as CategoryCollection;
use Klevu\IndexingCategories\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Eav\Model\Entity;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\ExpressionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\GroupRepositoryInterface;
use Psr\Log\LoggerInterface;

class CategoryEntityProvider implements EntityProviderInterface
{
    public const ENTITY_SUBTYPE_CATEGORY = 'category';

    /**
     * @var CategoryCollectionFactory
     */
    private readonly CategoryCollectionFactory $categoryCollectionFactory;
    /**
     * @var ScopeConfigProviderInterface
     */
    private readonly ScopeConfigProviderInterface $syncEnabledProvider;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var GroupRepositoryInterface
     */
    private readonly GroupRepositoryInterface $groupRepository;
    /**
     * @var string
     */
    private readonly string $entitySubtype;
    /**
     * @var int|null
     */
    private readonly ?int $batchSize;
    /**
     * @var ExpressionFactory
     */
    private readonly ExpressionFactory $expressionFactory;

    /**
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param ScopeConfigProviderInterface $syncEnabledProvider
     * @param LoggerInterface $logger
     * @param GroupRepositoryInterface $groupRepository
     * @param string $entitySubtype
     * @param int|null $batchSize
     * @param ValidatorInterface|null $batchSizeValidator
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        CategoryCollectionFactory $categoryCollectionFactory,
        ScopeConfigProviderInterface $syncEnabledProvider,
        LoggerInterface $logger,
        GroupRepositoryInterface $groupRepository,
        string $entitySubtype = self::ENTITY_SUBTYPE_CATEGORY,
        ?int $batchSize = null,
        ?ValidatorInterface $batchSizeValidator = null,
        ?ExpressionFactory $expressionFactory = null,
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->syncEnabledProvider = $syncEnabledProvider;
        $this->logger = $logger;
        $this->groupRepository = $groupRepository;
        $this->entitySubtype = $entitySubtype;

        $objectManager = ObjectManager::getInstance();
        $batchSizeValidator = $batchSizeValidator ?: $objectManager->get(BatchSizeValidator::class);
        if (!$batchSizeValidator->isValid($batchSize)) {
            throw new \InvalidArgumentException(
                message: sprintf(
                    'Invalid Batch Size: %s',
                    implode(', ', $batchSizeValidator->getMessages()),
                ),
            );
        }
        $this->batchSize = $batchSize;
        $this->expressionFactory = $expressionFactory
            ?: $objectManager->get(ExpressionFactory::class);
    }

    /**
     * @param StoreInterface|null $store
     * @param int[]|null $entityIds
     *
     * @return \Generator<CategoryInterface[]>|null
     * @throws LocalizedException
     */
    public function get(?StoreInterface $store = null, ?array $entityIds = []): ?\Generator
    {
        if (!$this->syncEnabledProvider->get()) {
            return null;
        }
        $currentEntityId = 0;
        while (true) {
            $categoryCollection = $this->getCollection(
                store: $store,
                entityIds: $entityIds,
                pageSize: $this->batchSize,
                currentEntityId: $currentEntityId + 1,
            );
            if (!$categoryCollection->getSize()) {
                break;
            }
            /** @var CategoryInterface[] $categories */
            $categories = $categoryCollection->getItems();
            yield $categories;
            $lastCategory = array_pop($categories);
            $currentEntityId = $lastCategory->getId();
            if (null === $this->batchSize || $categoryCollection->getSize() < $this->batchSize) {
                break;
            }
        }
    }

    /**
     * @return string
     */
    public function getEntitySubtype(): string
    {
        return $this->entitySubtype;
    }

    /**
     * @param StoreInterface|null $store
     * @param int[]|null $entityIds
     * @param int|null $pageSize
     * @param int $currentEntityId
     *
     * @return CategoryCollection
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getCollection(
        ?StoreInterface $store,
        ?array $entityIds,
        ?int $pageSize = null,
        int $currentEntityId = 1,
    ): CategoryCollection {
        // @TODO extract to own class
        /** @var CategoryCollection $categoryCollection */
        $categoryCollection = $this->categoryCollectionFactory->create();
        $categoryCollection->addAttributeToSelect(attribute: '*');
        $categoryCollection->addFieldToFilter('path', ['neq' => '1']);
        if (null !== $pageSize) {
            $categoryCollection->setPageSize($pageSize);
            $categoryCollection->addFieldToFilter(Entity::DEFAULT_ENTITY_ID_FIELD, ['gteq' => $currentEntityId]);
        }
        $categoryCollection->setOrder(Entity::DEFAULT_ENTITY_ID_FIELD, Select::SQL_ASC);
        if ($store) {
            $categoryCollection->setStore(store: (int)$store->getId());
            $group = $this->groupRepository->get(id: $store->getStoreGroupId());
            $categoryCollection->addPathsFilter(
                paths: [Category::TREE_ROOT_ID . '/' . $group->getRootCategoryId() . '/'],
            );
            $select = $categoryCollection->getSelect();
            $select->columns(
                cols: [
                    'store_id' => $this->expressionFactory->create([
                        'expression' => $store->getId(),
                    ]),
                ],
            );
        }
        if ($entityIds) {
            $categoryCollection->addFieldToFilter(
                Entity::DEFAULT_ENTITY_ID_FIELD,
                ['in' => implode(',', $entityIds)],
            );
        }
        $this->logQuery(collection: $categoryCollection);

        return $categoryCollection;
    }

    /**
     * @param CategoryCollection $collection
     *
     * @return void
     */
    private function logQuery(CategoryCollection $collection): void
    {
        $this->logger->debug(
            message: 'Method: {method}, Debug: {message}',
            context: [
                'method' => __METHOD__,
                'message' =>
                    sprintf(
                        'Category Entity Provider Query: %s',
                        $collection->getSelect()->__toString(),
                    ),
            ],
        );
    }
}
