<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Service\Provider;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

interface CategoryUrlProviderInterface
{
    /**
     * @param CategoryInterface $category
     * @param int|null $storeId
     *
     * @return string
     */
    public function getForCategory(
        CategoryInterface $category,
        ?int $storeId,
    ): string;

    /**
     * @param int $categoryId
     * @param int|null $storeId
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getForCategoryId(
        int $categoryId,
        ?int $storeId,
    ): string;
}
