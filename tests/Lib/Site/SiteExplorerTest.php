<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Tests\Lib\Site;

use Ibexa\Core\MVC\Symfony\SiteAccess;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Masilia\IbexaAiCraft\Catalog\ContentCatalog;
use Masilia\IbexaAiCraft\Site\ParentCandidates;
use Masilia\IbexaAiCraft\Site\SiteExplorer;
use Masilia\IbexaAiCraft\Site\SiteTree;
use Masilia\IbexaAiCraft\Support\Conventions;
use Masilia\Contracts\IbexaAi\Site\SiteLocations;
use Masilia\Contracts\IbexaBlocks\ShownBlocks;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SiteExplorerTest extends TestCase
{
    use \Masilia\IbexaAiCraft\Tests\Lib\Catalog\ContentCatalogFactoryTrait;
    use \Masilia\IbexaAiCraft\Tests\Lib\Catalog\BlockCatalogFactoryTrait;

    /** @var list<array{string, int}> What the tree and the candidates were asked for */
    private array $asked = [];

    /**
     * @param list<string> $siteaccesses
     */
    private function explorer(array $siteaccesses, ?ContentCatalog $catalog = null, ?ShownBlocks $shown = null, bool $treeFails = false): SiteExplorer
    {
        $service = $this->createMock(SiteAccessServiceInterface::class);
        $service->method('getAll')->willReturn(array_map(static fn (string $name): SiteAccess => new SiteAccess($name), $siteaccesses));

        $locations = $this->createMock(SiteLocations::class);
        $locations->method('rootLocationId')->willReturnCallback(static fn (string $siteaccess): ?int => $siteaccess === 'no_root' ? null : 42);

        $tree = $this->createMock(SiteTree::class);
        $tree->method('under')->willReturnCallback(function (int $root, int $depth) use ($treeFails): array {
            $this->asked[] = ['tree', $root];
            if ($treeFails) {
                throw new \RuntimeException('search is down');
            }

            return ['root_location_id' => $root, 'total_items' => 1, 'children' => [['location_id' => 50, 'content_id' => 60, 'content_type' => 'page', 'name' => 'About']]];
        });
        $parents = $this->createMock(ParentCandidates::class);
        $parents->method('under')->willReturnCallback(function (int $root, string $type): array {
            $this->asked[] = ['parents:' . $type, $root];

            return [['location_id' => 50, 'name' => 'About', 'content_type' => $type]];
        });

        $blocks = $this->createBlockCatalog([
            'hero_banner' => ['name' => 'Hero', 'fields' => ['title' => 'ibexa_string']],
            'info_cards' => ['name' => 'Cards', 'fields' => ['title' => 'ibexa_string']],
            'orphan' => ['name' => 'Orphan', 'fields' => ['title' => 'ibexa_string']],
        ]);

        return new SiteExplorer($service, $locations, $tree, $parents, $blocks, new NullLogger(), $catalog, new Conventions(), $shown);
    }

    private function pageCatalog(): ContentCatalog
    {
        return $this->createContentCatalog(['Content' => ['page' => ['name' => 'Page', 'fields' => ['blocks' => [
            'type' => 'ibexa_object_relation_list',
            'settings' => ['selectionContentTypes' => ['hero_banner', 'info_cards', 'orphan']],
        ]]]]]);
    }

    public function testWithoutAMatchOnlyTheSiteaccessesAreListed(): void
    {
        $result = $this->explorer(['admin', 'partner'])->explore('unknown_site');

        self::assertSame(['admin', 'partner'], $result->siteaccesses);
        self::assertNull($result->matchedSiteaccess);
        self::assertNull($result->rootLocationId);
        self::assertSame([], $result->siteStructure);
        self::assertSame([], $result->parentCandidates);
        self::assertSame([], $this->asked, 'nothing is read for no site');
    }

    public function testTheSiteIsMatchedLoosely(): void
    {
        self::assertSame('acmeshop', $this->explorer(['acmeshop', 'partner'])->explore('acme shop')->matchedSiteaccess);
        self::assertSame('partner_test', $this->explorer(['partner_test'])->explore('partner-test')->matchedSiteaccess);
        self::assertSame('admin', $this->explorer(['admin', 'admin_acme'])->explore('admin')->matchedSiteaccess, 'an exact name wins');
    }

    public function testAMatchedSiteGivesItsRootTreeParentsAndBlocks(): void
    {
        $result = $this->explorer(['acmeshop'], $this->pageCatalog())->explore('acmeshop');

        self::assertSame(42, $result->rootLocationId);
        self::assertSame('acmeshop', $result->siteStructure['siteaccess']);
        self::assertSame('About', $result->siteStructure['children'][0]['name']);
        self::assertSame([['location_id' => 50, 'name' => 'About', 'content_type' => 'page', 'siteaccess' => 'acmeshop']], $result->parentCandidates);
        self::assertSame(['hero_banner', 'info_cards', 'orphan'], array_keys($result->blockTypes));
        self::assertSame(['hero_banner', 'info_cards', 'orphan'], $result->parentBlocksAllowedTypes);
        self::assertSame([['tree', 42], ['parents:page', 42]], $this->asked, 'the pages are the conventions\' page type');
    }

    public function testASiteWithNoRootGivesNoTreeAndNoParents(): void
    {
        $result = $this->explorer(['no_root'])->explore('no_root');

        self::assertSame('no_root', $result->matchedSiteaccess);
        self::assertSame([], $result->siteStructure);
        self::assertSame([], $result->parentCandidates);
        self::assertSame([], $this->asked);
    }

    public function testATreeThatCannotBeReadLeavesTheRestOfTheAnswer(): void
    {
        $result = $this->explorer(['acmeshop'], $this->pageCatalog(), null, true)->explore('acmeshop');

        self::assertFalse($result->hasError());
        self::assertSame([], $result->siteStructure);
        self::assertNotSame([], $result->parentCandidates);
        self::assertNotSame([], $result->blockTypes);
    }

    public function testTheBlocksAPageAcceptsComeFromTheCatalogWhenThereIsOne(): void
    {
        self::assertSame([], $this->explorer(['acmeshop'])->explore('acmeshop')->parentBlocksAllowedTypes, 'no catalog');

        $articles = $this->createContentCatalog(['Content' => ['article' => ['name' => 'Article', 'fields' => ['title' => 'ibexa_string']]]]);
        self::assertSame([], $this->explorer(['acmeshop'], $articles)->explore('acmeshop')->parentBlocksAllowedTypes, 'no page type');
    }

    public function testBlocksTheThemeOfTheSiteHasNoTemplateForAreNotOffered(): void
    {
        $shown = new class() implements ShownBlocks {
            public ?string $asked = null;

            public function forSiteaccess(string $siteaccess): array
            {
                $this->asked = $siteaccess;

                return ['hero_banner', 'info_cards'];
            }
        };

        $result = $this->explorer(['acmeshop'], $this->pageCatalog(), $shown)->explore('acmeshop');

        self::assertSame('acmeshop', $shown->asked);
        self::assertSame(['hero_banner', 'info_cards'], array_column($result->blockTypes, 'identifier'));
        self::assertSame(['hero_banner', 'info_cards'], $result->parentBlocksAllowedTypes);
        self::assertSame(['orphan'], $result->notShownBlockTypes);
    }
}
