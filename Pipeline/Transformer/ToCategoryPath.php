<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Pipeline\Transformer;

use Klevu\IndexingCategories\Service\Provider\CategoryPathProviderInterface;
use Klevu\Pipelines\Exception\Transformation\InvalidInputDataException;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Transformer\TransformerInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class ToCategoryPath implements TransformerInterface
{
    /**
     * @var CategoryPathProviderInterface
     */
    private readonly CategoryPathProviderInterface $categoryPathProvider;

    /**
     * @param CategoryPathProviderInterface $categoryPathProvider
     */
    public function __construct(
        CategoryPathProviderInterface $categoryPathProvider,
    ) {
        $this->categoryPathProvider = $categoryPathProvider;
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

        if (!is_numeric($data)) {
            throw new InvalidInputDataException(
                transformerName: $this::class,
                expectedType: 'numeric-string|int|float',
                arguments: $arguments,
                data: $data,
            );
        }

        return $this->categoryPathProvider->getForCategoryId(
            categoryId: (int)$data,
        );
    }
}
