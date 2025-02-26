<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Pipeline\Transformer;

use Klevu\IndexingCategories\Pipeline\Provider\Argument\Transformer\ToCategoryPathArgumentProvider;
use Klevu\IndexingCategories\Service\Provider\CategoryPathProviderInterface;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Transformer\TransformerInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class ToCategoryPath implements TransformerInterface
{
    /**
     * @var CategoryPathProviderInterface
     */
    private readonly CategoryPathProviderInterface $categoryPathProvider;
    /**
     * @var ToCategoryPathArgumentProvider
     */
    private readonly ToCategoryPathArgumentProvider $argumentProvider;

    /**
     * @param CategoryPathProviderInterface $categoryPathProvider
     * @param ToCategoryPathArgumentProvider $argumentProvider
     */
    public function __construct(
        CategoryPathProviderInterface $categoryPathProvider,
        ToCategoryPathArgumentProvider $argumentProvider,
    ) {
        $this->categoryPathProvider = $categoryPathProvider;
        $this->argumentProvider = $argumentProvider;
    }

    /**
     * @param mixed $data
     * @param ArgumentIterator|null $arguments
     * @param \ArrayAccess<string|int, mixed>|null $context
     *
     * @return string|null
     * @throws InvalidInputDataException
     * @throws NoSuchEntityException
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
        $storeIdArgumentValue = $this->argumentProvider->getStoreIdArgumentValue(
            arguments: $arguments,
            extractionPayload: $data,
            extractionContext: $context,
        );

        return $this->categoryPathProvider->getForCategory(
            category: $data,
            storeId: $storeIdArgumentValue,
        );
    }
}
