<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Pipeline\Transformer;

use Klevu\IndexingCategories\Pipeline\Provider\Argument\Transformer\ToCategoryUrlArgumentProvider;
use Klevu\IndexingCategories\Service\Provider\CategoryUrlProviderInterface;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Transformer\TransformerInterface;
use Magento\Catalog\Api\Data\CategoryInterface;

class ToCategoryUrl implements TransformerInterface
{
    /**
     * @var ToCategoryUrlArgumentProvider
     */
    private readonly ToCategoryUrlArgumentProvider $argumentProvider;
    /**
     * @var CategoryUrlProviderInterface
     */
    private readonly CategoryUrlProviderInterface $categoryUrlProvider;

    /**
     * @param ToCategoryUrlArgumentProvider $argumentProvider
     * @param CategoryUrlProviderInterface $categoryUrlProvider
     */
    public function __construct(
        ToCategoryUrlArgumentProvider $argumentProvider,
        CategoryUrlProviderInterface $categoryUrlProvider,
    ) {
        $this->argumentProvider = $argumentProvider;
        $this->categoryUrlProvider = $categoryUrlProvider;
    }

    /**
     * @param mixed $data
     * @param ArgumentIterator|null $arguments
     * @param \ArrayAccess<string|int, mixed>|null $context
     *
     * @return string|null
     * @throws InvalidInputDataException
     */
    public function transform(
        mixed $data,
        ?ArgumentIterator $arguments = null,
        ?\ArrayAccess $context = null, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
    ): ?string {
        if (null === $data) {
            return null;
        }
        if (!($data instanceof CategoryInterface)) {
            throw new InvalidInputDataException(
                transformerName: $this::class,
                expectedType: CategoryInterface::class,
                arguments: $arguments,
                data: $data,
            );
        }

        $storeId = $this->argumentProvider->getStoreIdArgumentValue(
            arguments: $arguments,
            extractionPayload: $data,
            extractionContext: $context,
        );

        return $this->categoryUrlProvider->getForCategory(
            category: $data,
            storeId: $storeId,
        );
    }
}
