<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Service\Provider;

use Klevu\IndexingApi\Service\Provider\AttributeProviderInterface;
use Klevu\IndexingApi\Service\Provider\AttributeProviderProviderInterface;
use Klevu\IndexingApi\Service\Provider\StaticAttributeProviderInterface;

class AttributeProviderProvider implements AttributeProviderProviderInterface
{
    /**
     * @var AttributeProviderInterface[]
     */
    private array $attributeProviders = [];
    /**
     * @var StaticAttributeProviderInterface[]
     */
    private array $staticAttributeProviders = [];

    /**
     * @param AttributeProviderInterface[] $attributeProviders
     * @param StaticAttributeProviderInterface[] $staticAttributeProviders
     */
    public function __construct(
        array $attributeProviders = [],
        array $staticAttributeProviders = [],
    ) {
        array_walk($attributeProviders, [$this, 'addAttributeProvider']);
        array_walk($staticAttributeProviders, [$this, 'addStaticAttributeProvider']);
    }

    /**
     * @return AttributeProviderInterface[]
     */
    public function get(): array
    {
        return $this->attributeProviders;
    }

    /**
     * @return StaticAttributeProviderInterface[]
     */
    public function getStaticProviders(): array
    {
        return $this->staticAttributeProviders;
    }

    /**
     * @param AttributeProviderInterface $attributeProvider
     * @param string $type
     *
     * @return void
     */
    private function addAttributeProvider(AttributeProviderInterface $attributeProvider, string $type): void
    {
        $this->attributeProviders[$type] = $attributeProvider;
    }

    /**
     * @param StaticAttributeProviderInterface $staticAttributeProvider
     * @param string $type
     *
     * @return void
     */
    private function addStaticAttributeProvider(
        StaticAttributeProviderInterface $staticAttributeProvider,
        string $type,
    ): void {
        if (!str_ends_with($type, '_STATIC')) {
            $type .= '_STATIC';
        }
        $this->attributeProviders[$type] = $staticAttributeProvider;
        $this->staticAttributeProviders[$type] = $staticAttributeProvider;
    }
}
