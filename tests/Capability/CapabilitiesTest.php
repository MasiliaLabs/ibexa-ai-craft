<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Tests\Capability;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Bundle\IbexaAiCraft\Capability\ItemTypesCapability;
use Masilia\Bundle\IbexaAiCraft\Capability\ListPreviewCapability;
use Masilia\Bundle\IbexaAiCraft\Capability\NewsletterSubscribersCapability;
use Masilia\Bundle\IbexaAiCraft\Capability\PageBlockMoveCapability;
use Masilia\Bundle\IbexaAiCraft\Capability\PageBlockRemoveCapability;
use Masilia\Bundle\IbexaAiCraft\Capability\PageBlocks;
use Masilia\Bundle\IbexaAiCraft\Capability\PageDescribeCapability;
use Masilia\Bundle\IbexaAiCraft\Capability\ThemeCheckCapability;
use Masilia\Bundle\IbexaAiCraft\Capability\ThemeCreateCapability;
use Masilia\Bundle\IbexaAiCraft\Capability\ThemeListCapability;
use Masilia\Contracts\IbexaAi\Content\ContentTypeSchemas;
use Masilia\Contracts\IbexaBlocks\ListPreview;
use Masilia\Contracts\IbexaBlocks\ShownViews;
use Masilia\Contracts\IbexaBlocks\Themes;
use Masilia\Bundle\IbexaAiCraft\Capability\AddBlocksCapability;
use Masilia\Bundle\IbexaAiCraft\Capability\ConventionsCapability;
use Masilia\Bundle\IbexaAiCraft\Capability\CreateStructureCapability;
use Masilia\Bundle\IbexaAiCraft\Capability\ExploreCapability;
use Masilia\Bundle\IbexaAiCraft\Mining\ConventionMiner;
use Masilia\Bundle\IbexaAiCraft\Site\ItemsCreator;
use Masilia\Bundle\IbexaAiCraft\Site\SiteExplorer;
use Masilia\Bundle\IbexaAiCraft\Support\Conventions;
use Masilia\Bundle\IbexaAiCraft\Site\SiteBuilder;
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
            self::assertContains($capability->requires(), [[['content', 'read']], [['setup', 'administrate']]]);

            return;
        }

        self::assertNotSame([], $capability->requires());
        $preview = $capability->preview($capability->examples()[0]['arguments']);
        self::assertNotSame([], $preview->rows);
        self::assertNotSame('', $preview->title);
    }
}
