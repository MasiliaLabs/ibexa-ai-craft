<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Tests\Capability;

use Ibexa\Contracts\Core\Repository\Repository;
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
        $builder = (new \ReflectionClass(SiteBuilder::class))->newInstanceWithoutConstructor();
        $explorer = (new \ReflectionClass(SiteExplorer::class))->newInstanceWithoutConstructor();

        foreach ([
            new ExploreCapability($explorer),
            new AddBlocksCapability($creator, $repository, $conventions),
            new CreateStructureCapability($builder),
            new ConventionsCapability(new ConventionMiner($repository, $conventions), $test->createMock(SiteLocations::class), $repository),
        ] as $capability) {
            yield $capability->name() => [$capability];
        }
    }

    #[DataProvider('capabilities')]
    public function testItIsNamedDescribedAndFollowsItsOwnExamples(Capability $capability): void
    {
        self::assertMatchesRegularExpression('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $capability->name());
        self::assertStringStartsWith('site.', $capability->name());
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
            self::assertSame([['content', 'read']], $capability->requires());

            return;
        }

        self::assertNotSame([], $capability->requires());
        $preview = $capability->preview($capability->examples()[0]['arguments']);
        self::assertNotSame([], $preview->rows);
        self::assertNotSame('', $preview->title);
    }
}
