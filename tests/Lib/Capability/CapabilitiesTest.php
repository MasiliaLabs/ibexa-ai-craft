<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Tests\Lib\Capability;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Contracts\IbexaSites\SiteOptions;
use Masilia\Contracts\IbexaSites\SiteRegistry;
use Masilia\Contracts\IbexaSites\SiteSettings;
use Masilia\IbexaAiCraft\Capability\ItemTypesCapability;
use Masilia\IbexaAiCraft\Capability\SiteConfigCreateCapability;
use Masilia\IbexaAiCraft\Capability\SiteConfigDefaultSiteaccessCapability;
use Masilia\IbexaAiCraft\Capability\SiteConfigDeleteCapability;
use Masilia\IbexaAiCraft\Capability\SiteConfigDescribeCapability;
use Masilia\IbexaAiCraft\Capability\SiteConfigListCapability;
use Masilia\IbexaAiCraft\Capability\SiteConfigSetStatusCapability;
use Masilia\IbexaAiCraft\Capability\SiteConfigUpdateCapability;
use Masilia\IbexaAiCraft\Site\SiteConfigs;
use Masilia\IbexaAiCraft\Capability\ListPreviewCapability;
use Masilia\IbexaAiCraft\Capability\NewsletterSubscribersCapability;
use Masilia\IbexaAiCraft\Capability\PageBlockMoveCapability;
use Masilia\IbexaAiCraft\Capability\PageBlockRemoveCapability;
use Masilia\IbexaAiCraft\Capability\PageBlocks;
use Masilia\IbexaAiCraft\Capability\PageDescribeCapability;
use Masilia\IbexaAiCraft\Capability\ThemeCheckCapability;
use Masilia\IbexaAiCraft\Capability\ThemeCreateCapability;
use Masilia\IbexaAiCraft\Capability\ThemeListCapability;
use Masilia\Contracts\IbexaAi\Content\ContentTypeSchemas;
use Masilia\Contracts\IbexaBlocks\ListPreview;
use Masilia\Contracts\IbexaBlocks\ShownViews;
use Masilia\Contracts\IbexaBlocks\Themes;
use Masilia\IbexaAiCraft\Capability\AddBlocksCapability;
use Masilia\IbexaAiCraft\Capability\ConventionsCapability;
use Masilia\IbexaAiCraft\Capability\CreateStructureCapability;
use Masilia\IbexaAiCraft\Capability\ExploreCapability;
use Masilia\IbexaAiCraft\Mining\ConventionMiner;
use Masilia\IbexaAiCraft\Site\ItemsCreator;
use Masilia\IbexaAiCraft\Site\SiteExplorer;
use Masilia\IbexaAiCraft\Support\Conventions;
use Masilia\IbexaAiCraft\Site\SiteBuilder;
use Masilia\Contracts\IbexaAi\Content\ContentWriter;
use Masilia\Contracts\IbexaAi\Content\Languages;
use Masilia\Contracts\IbexaAi\Site\SiteLocations;
use Masilia\Contracts\IbexaAi\Os\Capability;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Masilia\IbexaAi\Os\SchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What the OS asks of every capability (see CapabilityContractTest of masilia/ibexa-ai), for those of this package.
 */
final class CapabilitiesTest extends TestCase
{
    /**
     * @return iterable<string, array{0: Capability}>
     */
    public static function capabilities(): iterable
    {
        $test = new self('capabilities');
        $repository = $test->createMock(Repository::class);
        $conventions = new Conventions();
        $creator = new ItemsCreator($repository, $test->createMock(ContentWriter::class), $test->createMock(Languages::class), $conventions);
        $pages = new PageBlocks($repository, $conventions);
        $registry = $test->createMock(SiteRegistry::class);
        $settings = $test->createMock(SiteSettings::class);
        $configs = new SiteConfigs($registry, $repository);
        $builder = (new \ReflectionClass(SiteBuilder::class))->newInstanceWithoutConstructor();
        $explorer = (new \ReflectionClass(SiteExplorer::class))->newInstanceWithoutConstructor();

        foreach ([
            new ExploreCapability($explorer),
            new AddBlocksCapability($creator, $repository, $conventions),
            new CreateStructureCapability($builder),
            new ConventionsCapability(new ConventionMiner($repository, $conventions), $test->createMock(SiteLocations::class), $repository),
            new PageDescribeCapability($pages, $conventions),
            new PageBlockMoveCapability($pages, $test->createMock(ContentWriter::class), $test->createMock(Languages::class), $conventions),
            new PageBlockRemoveCapability($pages, $test->createMock(ContentWriter::class), $test->createMock(Languages::class), $conventions),
            new ThemeListCapability($test->createMock(Themes::class)),
            new ThemeCheckCapability($test->createMock(Themes::class)),
            new ThemeCreateCapability($test->createMock(Themes::class)),
            new ListPreviewCapability($test->createMock(ListPreview::class)),
            new NewsletterSubscribersCapability(),
            new SiteConfigListCapability($registry, $settings, $configs),
            new SiteConfigDescribeCapability($registry, $settings, $test->createMock(SiteOptions::class), $configs),
            new SiteConfigCreateCapability($registry, $configs),
            new SiteConfigUpdateCapability($registry, $configs),
            new SiteConfigSetStatusCapability($registry, $configs),
            new SiteConfigDeleteCapability($registry, $configs),
            new SiteConfigDefaultSiteaccessCapability($settings),
            new ItemTypesCapability($test->createMock(ShownViews::class), $test->createMock(ContentTypeService::class), $test->createMock(ContentTypeSchemas::class), $conventions),
        ] as $capability) {
            yield $capability->name() => [$capability];
        }
    }

    #[DataProvider('capabilities')]
    public function testItIsNamedDescribedAndFollowsItsOwnExamples(Capability $capability): void
    {
        self::assertMatchesRegularExpression('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $capability->name());
        self::assertContains(explode('.', $capability->name())[0], ['site', 'page', 'theme', 'list', 'newsletter', 'item']);
        self::assertNotSame('', $capability->summary());
        self::assertGreaterThan(40, strlen($capability->description()));
        self::assertSame('object', $capability->inputSchema()['type']);
        self::assertNotSame([], $capability->examples());
        foreach ($capability->examples() as $example) {
            self::assertSame([], SchemaValidator::validate($capability->inputSchema(), $example['arguments']), $capability->name() . ': an example breaks its own schema');
        }
    }

    #[DataProvider('capabilities')]
    public function testWhatChangesSomethingNamesItsPoliciesAndPreviewsItself(Capability $capability): void
    {
        if ($capability->risk() === Risk::READ) {
            self::assertContains($capability->requires(), [[['content', 'read']], [['setup', 'administrate']], [['masilia_site', 'view']]]);

            return;
        }

        self::assertNotSame([], $capability->requires());
        $preview = $capability->preview($capability->examples()[0]['arguments']);
        self::assertNotSame([], $preview->rows);
        self::assertNotSame('', $preview->title);
    }
}
