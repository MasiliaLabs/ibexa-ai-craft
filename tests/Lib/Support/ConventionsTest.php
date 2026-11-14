<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Tests\Lib\Support;

use Masilia\IbexaAiCraft\Support\Conventions;
use PHPUnit\Framework\TestCase;

final class ConventionsTest extends TestCase
{
    public function testTheDefaultsAreTheMasiliaWay(): void
    {
        $conventions = new Conventions();

        self::assertSame('Blocks', $conventions->blockGroup);
        self::assertSame('page', $conventions->pageType);
        self::assertSame('blocks', $conventions->blocksField);
        self::assertSame('items', $conventions->itemsField);
        self::assertContains('Block items', $conventions->contentGroups);
        self::assertSame(['site', 'home_page', 'layout_config', 'folder'], [$conventions->siteType, $conventions->homePageType, $conventions->layoutType, $conventions->folderType]);
    }

    public function testTheTypesOfASiteAreSettingsToo(): void
    {
        $conventions = Conventions::fromSettings(['site_type' => 'website', 'home_page_type' => 'landing', 'layout_type' => 'chrome', 'folder_type' => 'directory']);

        self::assertSame(['website', 'landing', 'chrome', 'directory'], [$conventions->siteType, $conventions->homePageType, $conventions->layoutType, $conventions->folderType]);
        self::assertSame('page', $conventions->pageType);
    }

    public function testAProjectSetsItsOwnAndKeepsTheRest(): void
    {
        $conventions = Conventions::fromSettings(['block_group' => 'Sections', 'blocks_field' => 'sections', 'content_groups' => ['Pages']]);

        self::assertSame('Sections', $conventions->blockGroup);
        self::assertSame('sections', $conventions->blocksField);
        self::assertSame(['Pages'], $conventions->contentGroups);
        self::assertSame('page', $conventions->pageType, 'what is not set keeps its default');
    }
}
