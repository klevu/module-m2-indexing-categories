<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCategories\Observer\Admin\System\Config;

use Klevu\IndexingApi\Service\Action\CreateCronScheduleActionInterface;
use Klevu\IndexingCategories\Service\Determiner\DisabledCategoriesIsIndexableCondition;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class UpdateCategorySyncSettingsObserver implements ObserverInterface
{
    /**
     * @var CreateCronScheduleActionInterface
     */
    private readonly CreateCronScheduleActionInterface $createCronScheduleAction;

    /**
     * @param CreateCronScheduleActionInterface $createCronScheduleAction
     */
    public function __construct(CreateCronScheduleActionInterface $createCronScheduleAction)
    {
        $this->createCronScheduleAction = $createCronScheduleAction;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $changedPaths = (array)$observer->getData('changed_paths');
        if (
            !in_array(
                needle: DisabledCategoriesIsIndexableCondition::XML_PATH_EXCLUDE_DISABLED_CATEGORIES,
                haystack: $changedPaths,
                strict: true,
            )
        ) {
            return;
        }

        $this->createCronScheduleAction->execute();
    }
}
