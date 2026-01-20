<?php


/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Service;

use Klevu\Indexing\Service\KlevuToMagentoEntityTypeMap;
use Klevu\IndexingApi\Api\KlevuToMagentoEntityTypeMapInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class KlevuToMagentoEntityTypeMapTest extends TestCase
{
    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @return void
     */
    public function testReturnsProductMappingFromConcreteImplementation(): void
    {
        /** @var KlevuToMagentoEntityTypeMap $klevuToMagentoEntityTypeMap */
        $klevuToMagentoEntityTypeMap = $this->objectManager->get(KlevuToMagentoEntityTypeMap::class);

        $this->assertSame(
            expected: 'KLEVU_CATEGORY',
            actual: $klevuToMagentoEntityTypeMap->getKlevuEntityTypeForMagentoEntityTypeId(3),
        );
        $this->assertSame(
            expected: [3],
            actual: $klevuToMagentoEntityTypeMap->getMagentoEntityTypeIdsForKlevuEntityType('KLEVU_CATEGORY'),
        );
    }

    /**
     * @return void
     */
    public function testReturnsProductMappingFromInterface(): void
    {
        /** @var KlevuToMagentoEntityTypeMapInterface $klevuToMagentoEntityTypeMap */
        $klevuToMagentoEntityTypeMap = $this->objectManager->get(KlevuToMagentoEntityTypeMapInterface::class);

        $this->assertSame(
            expected: 'KLEVU_CATEGORY',
            actual: $klevuToMagentoEntityTypeMap->getKlevuEntityTypeForMagentoEntityTypeId(3),
        );
        $this->assertSame(
            expected: [3],
            actual: $klevuToMagentoEntityTypeMap->getMagentoEntityTypeIdsForKlevuEntityType('KLEVU_CATEGORY'),
        );
    }
}