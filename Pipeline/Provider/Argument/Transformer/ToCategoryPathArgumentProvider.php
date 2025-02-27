<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Pipeline\Provider\Argument\Transformer;

use Klevu\IndexingCategories\Pipeline\Transformer\ToCategoryPath;
use Klevu\IndexingProducts\Pipeline\Transformer\ToRatingCount;
use Klevu\Pipelines\Exception\Transformation\InvalidTransformationArgumentsException;
use Klevu\Pipelines\Model\ArgumentIterator;
use Klevu\Pipelines\Provider\ArgumentProviderInterface;

class ToCategoryPathArgumentProvider
{
    public const ARGUMENT_INDEX_STORE_ID = 0;

    /**
     * @var ArgumentProviderInterface
     */
    private ArgumentProviderInterface $argumentProvider;

    /**
     * @param ArgumentProviderInterface $argumentProvider
     */
    public function __construct(ArgumentProviderInterface $argumentProvider)
    {
        $this->argumentProvider = $argumentProvider;
    }

    /**
     * @param ArgumentIterator|null $arguments
     * @param mixed|null $extractionPayload
     * @param \ArrayAccess<int|string, mixed>|null $extractionContext
     *
     * @return int|null
     */
    public function getStoreIdArgumentValue(
        ?ArgumentIterator $arguments,
        mixed $extractionPayload = null,
        ?\ArrayAccess $extractionContext = null,
    ): ?int {
        $argumentValue = $this->argumentProvider->getArgumentValueWithExtractionExpansion(
            arguments: $arguments,
            argumentKey: self::ARGUMENT_INDEX_STORE_ID,
            defaultValue: null,
            extractionPayload: $extractionPayload,
            extractionContext: $extractionContext,
        );
        if (null !== $argumentValue && !is_numeric($argumentValue)) {
            throw new InvalidTransformationArgumentsException(
                transformerName: ToRatingCount::class,
                errors: [
                    sprintf(
                        'Store ID argument (%s) for %s must be integer or null; Received %s',
                        self::ARGUMENT_INDEX_STORE_ID,
                        ToCategoryPath::class,
                        is_scalar($argumentValue)
                            ? $argumentValue
                            : get_debug_type($argumentValue),
                    ),
                ],
                arguments: $arguments,
                data: $extractionPayload,
            );
        }

        return $argumentValue;
    }
}
