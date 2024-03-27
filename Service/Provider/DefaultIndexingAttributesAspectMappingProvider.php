<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Service\Provider;

use Klevu\IndexingApi\Service\Provider\DefaultIndexingAttributesAspectMappingProviderInterface;
use Klevu\IndexingCategories\Model\Source\Aspect;

class DefaultIndexingAttributesAspectMappingProvider implements DefaultIndexingAttributesAspectMappingProviderInterface
{
    /**
     * @var array<string, Aspect>
     */
    private array $aspectMapping = [
        'description' => Aspect::ALL,
        'display_mode' => Aspect::ALL,
        'is_active' => Aspect::ALL,
        'is_anchor' => Aspect::ALL,
        'is_changed_product_list' => Aspect::ALL,
        'meta_description' => Aspect::ALL,
        'meta_keywords' => Aspect::ALL,
        'name' => Aspect::ALL,
        'path' => Aspect::ALL,
        'path_ids' => Aspect::ALL,
        'parent_id' => Aspect::ALL,
        'store_id' => Aspect::ALL,
        'store_ids' => Aspect::ALL,
        'url_key' => Aspect::ALL,
        'url_path' => Aspect::ALL,
    ];

    /**
     * @return array<string, Aspect>
     */
    public function get(): array
    {
        return $this->aspectMapping;
    }
}
