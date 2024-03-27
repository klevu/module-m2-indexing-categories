<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Model\Source;

use Klevu\Configuration\Traits\EnumTrait;

enum Aspect: int
{
    use EnumTrait;

    case NONE = 0;
    case ALL = 1;

    /**
     * @return string
     */
    public function label(): string
    {
        return match ($this) //phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext
        {
            self::NONE => 'Nothing',
            self::ALL => 'Everything',
        };
    }
}
