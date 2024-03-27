<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Test\Integration\Service;

use Klevu\Indexing\Exception\InvalidIndexingRecordDataTypeException;
use Klevu\IndexingApi\Service\EntityIndexingRecordCreatorServiceInterface;
use Klevu\IndexingCategories\Service\EntityIndexingRecordCreatorService;
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Cms\PageFixturesPool;
use Klevu\TestFixtures\Cms\PageTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\CategoryFixturePool;

class EntityIndexingRecordCreatorServiceTest extends TestCase
{
    use CategoryTrait;
    use ObjectInstantiationTrait;
    use PageTrait;
    use TestImplementsInterfaceTrait;

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

        $this->implementationFqcn = EntityIndexingRecordCreatorService::class;
        $this->interfaceFqcn = EntityIndexingRecordCreatorServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->categoryFixturePool = $this->objectManager->get(CategoryFixturePool::class);
        $this->pageFixturesPool = $this->objectManager->get(PageFixturesPool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->pageFixturesPool->rollback();
        $this->categoryFixturePool->rollback();
    }

    public function testExecute_ThrowsException_WhenIncorrectEntityTypeProvided(): void
    {
        $this->createPage();
        $pageFixture = $this->pageFixturesPool->get('test_page');
        $page = $pageFixture->getPage();

        $this->expectException(InvalidIndexingRecordDataTypeException::class);
        $this->expectExceptionMessage(
            sprintf(
                '"entity" provided to %s, must be instance of %s',
                EntityIndexingRecordCreatorService::class,
                ExtensibleDataInterface::class,
            ),
        );

        $service = $this->instantiateTestObject();
        $service->execute(
            recordId: 1,
            entity: $page,
        );
    }

    public function testExecute_ThrowsException_WhenIncorrectParentEntityTypeProvided(): void
    {
        $this->createCategory();
        $categoryFixture = $this->categoryFixturePool->get('test_category');
        $category = $categoryFixture->getCategory();

        $this->createPage();
        $pageFixture = $this->pageFixturesPool->get('test_page');
        $page = $pageFixture->getPage();

        $this->expectException(InvalidIndexingRecordDataTypeException::class);
        $this->expectExceptionMessage(
            sprintf(
                '"parent" provided to %s, must be instance of %s or null',
                EntityIndexingRecordCreatorService::class,
                ExtensibleDataInterface::class,
            ),
        );

        $service = $this->instantiateTestObject();
        $service->execute(
            recordId: 1,
            entity: $category,
            parent: $page,
        );
    }

    public function testExecute_ReturnsIndexingRecord_WithEntity(): void
    {
        $this->createCategory();
        $categoryFixture = $this->categoryFixturePool->get('test_category');
        $category = $categoryFixture->getCategory();

        $service = $this->instantiateTestObject();
        $result = $service->execute(
            recordId: 1,
            entity: $category,
        );

        $this->assertSame(
            expected: (int)$category->getId(),
            actual: (int)$result->getEntity()->getId(),
        );
        $this->assertNull(actual: $result->getParent());
    }

    public function testExecute_ReturnsIndexingRecord_WithAllData(): void
    {
        $this->createCategory([
            'key' => 'top_cat',
        ]);
        $topCategoryFixture = $this->categoryFixturePool->get('top_cat');
        $topCategory = $topCategoryFixture->getCategory();

        $this->createCategory([
            'key' => 'test_cat',
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_cat');
        $category = $categoryFixture->getCategory();

        $service = $this->instantiateTestObject();
        $result = $service->execute(
            recordId: 1,
            entity: $category,
            parent: $topCategory,

        );

        $this->assertSame(
            expected: (int)$category->getId(),
            actual: (int)$result->getEntity()->getId(),
        );
        $this->assertNull(
            actual: $result->getParent(),
        );
    }
}
