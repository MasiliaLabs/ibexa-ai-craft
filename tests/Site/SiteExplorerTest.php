<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Tests\Site;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\SiteAccess;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Masilia\Bundle\IbexaAiCraft\Catalog\ContentCatalog;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAiCraft\Tool\CraftToolName;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolRegistry;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;
use Masilia\Bundle\IbexaAiCraft\Site\SiteExplorer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SiteExplorerTest extends TestCase
{
    use \Masilia\IbexaAiCraft\Tests\Catalog\ContentCatalogFactoryTrait;
    private function makeService(array $names): SiteAccessServiceInterface
    {
        $service = $this->createMock(SiteAccessServiceInterface::class);
        $siteAccesses = [];
        foreach ($names as $name) {
            $sa = $this->createMock(SiteAccess::class);
            $sa->name = $name;
            $siteAccesses[] = $sa;
        }
        $service->method('getAll')->willReturn($siteAccesses);
        return $service;
    }

    private function makeConfigResolver(int $rootId): ConfigResolverInterface
    {
        $cr = $this->createMock(ConfigResolverInterface::class);
        $cr->method('getParameter')->willReturnCallback(
            function (string $param, ?string $namespace = null, $scope = null) use ($rootId) {
                if ($param === 'content.tree_root.location_id') {
                    return $rootId;
                }
                throw new \RuntimeException("Unknown param: $param");
            },
        );
        return $cr;
    }

    public function testExploreListsAllSiteaccesses(): void
    {
        $service = $this->makeService(['fossilexit', 'mattcch', 'admin']);
        $cr = $this->makeConfigResolver(42);
        $registry = new ToolRegistry();

        $explorer = new SiteExplorer($service, $cr, $registry, new NullLogger());
        $result = $explorer->explore('fossilexit');

        self::assertSame(['admin', 'fossilexit', 'mattcch'], $result->siteaccesses);
        self::assertSame('fossilexit', $result->matchedSiteaccess);
        self::assertSame(42, $result->rootLocationId);
    }

    public function testExploreFuzzyMatchesFossilExitToFossilexit(): void
    {
        $service = $this->makeService(['fossilexit', 'mattcch']);
        $cr = $this->makeConfigResolver(42);
        $registry = new ToolRegistry();

        $explorer = new SiteExplorer($service, $cr, $registry, new NullLogger());
        $result = $explorer->explore('fossil exit');

        self::assertSame('fossilexit', $result->matchedSiteaccess);
    }

    public function testExploreFuzzyMatchesWithUnderscoresAndDashes(): void
    {
        $service = $this->makeService(['mattcch_test']);
        $cr = $this->makeConfigResolver(42);
        $registry = new ToolRegistry();

        $explorer = new SiteExplorer($service, $cr, $registry, new NullLogger());
        $result = $explorer->explore('mattcch-test');

        self::assertSame('mattcch_test', $result->matchedSiteaccess);
    }

    public function testExploreReturnsNullMatchWhenNoSiteaccessMatches(): void
    {
        $service = $this->makeService(['admin', 'mattcch']);
        $cr = $this->makeConfigResolver(42);
        $registry = new ToolRegistry();

        $explorer = new SiteExplorer($service, $cr, $registry, new NullLogger());
        $result = $explorer->explore('unknown_site');

        self::assertNull($result->matchedSiteaccess);
        self::assertNull($result->rootLocationId);
        self::assertSame([], $result->siteStructure);
        self::assertSame([], $result->parentCandidates);
    }

    public function testExploreCallsAllThreeToolsWhenMatched(): void
    {
        $registry = new ToolRegistry();

        $browse = new class implements ToolInterface {
            public bool $called = false;
            public function getName(): string { return CraftToolName::BROWSE_SITE_STRUCTURE; }
            public function getDescription(): string { return 'mock'; }
            public function getParameters(): array { return []; }
            public function execute(array $params): ToolResult
            {
                $this->called = true;
                return ToolResult::ok('ok', ['children' => []]);
            }
        };

        $parent = new class implements ToolInterface {
            public bool $called = false;
            public function getName(): string { return CraftToolName::FIND_PARENT_CANDIDATES; }
            public function getDescription(): string { return 'mock'; }
            public function getParameters(): array { return []; }
            public function execute(array $params): ToolResult
            {
                $this->called = true;
                return ToolResult::ok('ok', ['candidates' => []]);
            }
        };

        $blocks = new class implements ToolInterface {
            public bool $called = false;
            public function getName(): string { return CraftToolName::LIST_BLOCKS; }
            public function getDescription(): string { return 'mock'; }
            public function getParameters(): array { return []; }
            public function execute(array $params): ToolResult
            {
                $this->called = true;
                return ToolResult::ok('ok', ['blocks' => []]);
            }
        };

        $registry = $registry->register($browse);
        $registry = $registry->register($parent);
        $registry = $registry->register($blocks);

        $service = $this->makeService(['fossilexit']);
        $cr = $this->makeConfigResolver(42);

        $explorer = new SiteExplorer($service, $cr, $registry, new NullLogger());
        $explorer->explore('fossilexit');

        self::assertTrue($browse->called);
        self::assertTrue($parent->called);
        self::assertTrue($blocks->called);
    }

    public function testExploreReturnsParentBlocksAllowedTypesFromContentCatalog(): void
    {
        $service = $this->makeService(['fossilexit']);
        $cr = $this->makeConfigResolver(42);
        $registry = new ToolRegistry();

        $catalog = $this->createContentCatalog([
            'Content' => [
                'page' => [
                    'name' => 'Page',
                    'fields' => [
                        'blocks' => [
                            'type' => 'ibexa_object_relation_list',
                            'settings' => ['selectionContentTypes' => ['hero_banner', 'text_block', 'info_cards']],
                        ],
                    ],
                ],
            ],
        ]);

        $explorer = new SiteExplorer($service, $cr, $registry, new NullLogger(), $catalog);
        $result = $explorer->explore('fossilexit');

        self::assertSame(['hero_banner', 'text_block', 'info_cards'], $result->parentBlocksAllowedTypes);
    }

    public function testExploreReturnsEmptyAllowedTypesWhenContentCatalogIsNull(): void
    {
        $service = $this->makeService(['fossilexit']);
        $cr = $this->makeConfigResolver(42);
        $registry = new ToolRegistry();

        $explorer = new SiteExplorer($service, $cr, $registry, new NullLogger(), null);
        $result = $explorer->explore('fossilexit');

        self::assertSame([], $result->parentBlocksAllowedTypes);
    }

    public function testExploreReturnsEmptyAllowedTypesWhenPageNotInCatalog(): void
    {
        $service = $this->makeService(['fossilexit']);
        $cr = $this->makeConfigResolver(42);
        $registry = new ToolRegistry();

        $catalog = $this->createContentCatalog([
            'Content' => [
                'article' => [
                    'name' => 'Article',
                    'fields' => ['title' => 'ibexa_string'],
                ],
            ],
        ]);

        $explorer = new SiteExplorer($service, $cr, $registry, new NullLogger(), $catalog);
        $result = $explorer->explore('fossilexit');

        self::assertSame([], $result->parentBlocksAllowedTypes);
    }
}
