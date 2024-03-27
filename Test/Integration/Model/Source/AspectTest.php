<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Model\Source;

use PHPUnit\Framework\TestCase;

class AspectTest extends TestCase
{
    public function testLabel_ReturnsExpectedValues(): void
    {
        $this->assertSame(expected: 'Nothing', actual: Aspect::NONE->label());
        $this->assertSame(expected: 'Everything', actual: Aspect::ALL->label());
    }
}
