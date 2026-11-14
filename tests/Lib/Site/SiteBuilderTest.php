<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Tests\Lib\Site;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Ibexa\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinitionCollection;
use Masilia\IbexaAiCraft\Site\SiteBuilder;
use Masilia\IbexaAiCraft\Support\Conventions;
use Masilia\Contracts\IbexaAi\Content\ContentWriter;
use Masilia\Contracts\IbexaAi\Content\Languages;
use Masilia\Contracts\IbexaAi\Content\PublishedContent;
use Masilia\Contracts\IbexaAi\Image\ImageGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SiteBuilderTest extends TestCase
{
    /** @var list<array{string, int, array<string, mixed>, string}> What was created: type, parent location, fields, language */
    private array $created = [];
    /** @var list<array{int, array<string, mixed>}> */
    private array $updated = [];
    /** @var list<string> */
    private array $transaction = [];
    private int $nextId = 100;

    private const ARGUMENTS = ['site_name' => 'Solar Atlas', 'domain' => 'solaratlas.org', 'pages' => [['title' => 'About'], ['title' => 'Projects', 'children' => [['title' => 'Morocco']]]]];

    /**
     * @param array<string, array<string, bool>>|null $types   Type => field => required (default: the Masilia types)
     * @param list<string>                            $failOn  Types whose creation fails
     */
    private function builder(?array $types = null, ?Conventions $conventions = null, bool $images = true, array $failOn = [], ?int $media = 43): SiteBuilder
    {
        $types ??= [
            'site' => ['title' => true, 'domain' => true, 'description' => false, 'favicon' => false, 'google_analytics_id' => false],
            'layout_config' => ['bo_title' => true, 'logo_image' => false],
            'home_page' => ['title' => true, 'blocks' => false, 'metas' => false],
            'page' => ['title' => true, 'description' => false, 'blocks' => false],
            'folder' => ['name' => true, 'short_name' => false],
        ];
        $typeService = $this->createMock(ContentTypeService::class);
        $typeService->method('loadContentTypeByIdentifier')->willReturnCallback(fn (string $id): ContentType => isset($types[$id]) ? $this->type($id, $types[$id]) : throw new NotFoundException('content type', $id));
        $locationService = $this->createMock(LocationService::class);
        $locationService->method('loadLocation')->willReturnCallback(fn (int $id): Location => in_array($id, [2, 43, 7], true) ? $this->createMock(Location::class) : throw new NotFoundException('location', $id));

        $repository = $this->createMock(Repository::class);
        $repository->method('getContentTypeService')->willReturn($typeService);
        $repository->method('getLocationService')->willReturn($locationService);
        $repository->method('beginTransaction')->willReturnCallback(function (): void { $this->transaction[] = 'begin'; });
        $repository->method('commit')->willReturnCallback(function (): void { $this->transaction[] = 'commit'; });
        $repository->method('rollback')->willReturnCallback(function (): void { $this->transaction[] = 'rollback'; });

        $writer = $this->createMock(ContentWriter::class);
        $writer->method('create')->willReturnCallback(function (string $type, array $parents, array $fields, string $language) use ($failOn): PublishedContent {
            if (in_array($type, $failOn, true)) {
                throw new \RuntimeException(sprintf('The %s could not be published.', $type));
            }
            $this->created[] = [$type, $parents[0], $fields, $language];
            $id = $this->nextId++;
            $content = $this->createMock(Content::class);
            $content->method('getId')->willReturn($id);
            $location = $this->createMock(Location::class);
            $location->method('getId')->willReturn($id + 1000);

            return new PublishedContent($content, $location);
        });
        $writer->method('update')->willReturnCallback(function (int $id, array $fields): Content {
            $this->updated[] = [$id, $fields];

            return $this->createMock(Content::class);
        });

        $languages = $this->createMock(Languages::class);
        $languages->method('resolve')->willReturnCallback(static fn (string $code): ?string => ['fr' => 'fre-FR', 'fre-FR' => 'fre-FR', 'en' => 'eng-GB'][$code] ?? null);
        $languages->method('default')->willReturn('eng-GB');

        $config = $this->createMock(ConfigResolverInterface::class);
        $config->method('getParameter')->willReturnCallback(static fn (string $name): int => match ($name) {
            'location_ids.content_structure' => 2,
            'location_ids.media' => $media ?? throw new \Ibexa\Core\MVC\Exception\ParameterNotFoundException($name, 'ibexa.site_access.config'),
        });

        $generator = $this->createMock(ImageGenerator::class);
        $generator->method('isConfigured')->willReturn($images);

        return new SiteBuilder($repository, $writer, $languages, $config, $generator, new NullLogger(), $conventions ?? new Conventions());
    }

    /**
     * @param array<string, bool> $fields Field identifier => required
     */
    private function type(string $identifier, array $fields): ContentType
    {
        $definitions = [];
        foreach ($fields as $field => $required) {
            $definitions[] = new FieldDefinition(['identifier' => $field, 'fieldTypeIdentifier' => 'ibexa_string', 'isRequired' => $required, 'names' => ['eng-GB' => $field], 'mainLanguageCode' => 'eng-GB']);
        }

        return new ContentType(['identifier' => $identifier, 'names' => ['eng-GB' => $identifier], 'mainLanguageCode' => 'eng-GB', 'fieldDefinitions' => new FieldDefinitionCollection($definitions)]);
    }

    public function testASiteThatCanBeBuiltHasNoProblem(): void
    {
        self::assertNull($this->builder()->problem(self::ARGUMENTS));
        self::assertNull($this->builder()->problem(self::ARGUMENTS + ['language' => 'fr', 'parent_location_id' => 7]));
        self::assertSame([], $this->created, 'nothing is written to find a problem');
    }

    public function testWhatTheBuildCannotDoIsSaidBeforeItIsApproved(): void
    {
        $builder = $this->builder();

        self::assertStringContainsString('The language "xx" does not exist', (string) $builder->problem(['language' => 'xx'] + self::ARGUMENTS));
        self::assertStringContainsString('Page 2 (level 1) has no title', (string) $builder->problem(['pages' => [['title' => 'A'], ['title' => ' ']]] + self::ARGUMENTS));
        self::assertStringContainsString('3 levels deep at most', (string) $builder->problem(['pages' => [['title' => 'A', 'children' => [['title' => 'B', 'children' => [['title' => 'C', 'children' => [['title' => 'D']]]]]]]]] + self::ARGUMENTS));
        self::assertStringContainsString('50 pages at most', (string) $builder->problem(['pages' => array_fill(0, 51, ['title' => 'P'])] + self::ARGUMENTS));
        self::assertStringContainsString('location 9 does not exist', (string) $builder->problem(['parent_location_id' => 9] + self::ARGUMENTS));
        self::assertStringContainsString('put the media under (none is configured)', (string) $this->builder(media: null)->problem(self::ARGUMENTS));
    }

    public function testTheContentTypesMustExistHaveTheFieldsASiteNeedsAndNoOtherRequiredOne(): void
    {
        $types = ['site' => ['title' => true, 'domain' => true, 'description' => false, 'favicon' => false], 'layout_config' => ['bo_title' => true], 'home_page' => ['title' => true, 'blocks' => false], 'page' => ['title' => true, 'description' => false, 'blocks' => false], 'folder' => ['name' => true]];

        self::assertStringContainsString('There is no content type "layout_config"', (string) $this->builder(array_diff_key($types, ['layout_config' => 1]))->problem(self::ARGUMENTS));
        self::assertStringContainsString('"page" has no field blocks', (string) $this->builder(['page' => ['title' => true, 'description' => false]] + $types)->problem(self::ARGUMENTS));
        self::assertStringContainsString('"site" has required fields a new site cannot fill: logo', (string) $this->builder(['site' => $types['site'] + ['logo' => true]] + $types)->problem(self::ARGUMENTS));
        self::assertNull($this->builder(['site' => ['title' => true, 'domain' => true, 'description' => false]] + $types)->problem(['generate_images' => false] + self::ARGUMENTS), 'no favicon field is needed when no image is made');
    }

    public function testTheSiteIsBuiltWhereTheSiteaccessSaysWithTheTypesOfTheConventionsInOneTransaction(): void
    {
        $conventions = new Conventions(pageType: 'landing', blocksField: 'sections', siteType: 'website', homePageType: 'front', layoutType: 'chrome', folderType: 'directory');
        $types = ['website' => ['title' => true, 'domain' => true, 'description' => false, 'favicon' => false], 'chrome' => ['bo_title' => true], 'front' => ['title' => true, 'sections' => false], 'landing' => ['title' => true, 'description' => false, 'sections' => false], 'directory' => ['name' => true]];
        $builder = $this->builder($types, $conventions, images: false);
        self::assertNull($builder->problem(self::ARGUMENTS));

        $built = $builder->build(self::ARGUMENTS + ['language' => 'fr']);

        $made = array_map(static fn (array $c): string => sprintf('%s under %d', $c[0], $c[1]), $this->created);
        self::assertSame([
            'website under 2', 'chrome under 1100', 'front under 1100',
            'directory under 43', 'directory under 1103', 'directory under 1103', 'directory under 1103',
            'landing under 1102', 'landing under 1102', 'landing under 1108',
        ], $made);
        self::assertSame(['title' => 'Solar Atlas', 'domain' => 'solaratlas.org', 'description' => ''], $this->created[0][2]);
        self::assertSame(['title' => 'Home', 'sections' => []], $this->created[2][2]);
        self::assertSame(['title' => 'Morocco', 'description' => '', 'sections' => []], $this->created[9][2]);
        self::assertSame(['fre-FR'], array_values(array_unique(array_column($this->created, 3))));
        self::assertSame(['begin', 'commit'], $this->transaction);
        self::assertSame(['site_id' => 100, 'home_page_id' => 102, 'home_page_location_id' => 1102, 'media_folder_id' => 103], array_intersect_key($built, array_flip(['site_id', 'home_page_id', 'home_page_location_id', 'media_folder_id'])));
        self::assertSame('Morocco', $built['pages'][1]['children'][0]['title']);
        self::assertFalse($built['favicon']);
    }

    public function testAFailureLeavesNothingBehind(): void
    {
        $builder = $this->builder(failOn: ['page']);

        try {
            $builder->build(self::ARGUMENTS);
            self::fail('the build should fail');
        } catch (\RuntimeException $e) {
            self::assertSame('The page could not be published.', $e->getMessage());
        }

        self::assertSame(['begin', 'rollback'], $this->transaction);
        self::assertSame([], $this->updated, 'no favicon for a site that is not there');
    }

    public function testTheFaviconIsAskedForAfterTheSiteIsThereAndOnlyWhenWanted(): void
    {
        $built = $this->builder()->build(self::ARGUMENTS);
        self::assertTrue($built['favicon']);
        self::assertSame(100, $this->updated[0][0]);
        self::assertSame('1:1', $this->updated[0][1]['favicon']['size']);
        self::assertStringContainsString('"Solar Atlas"', $this->updated[0][1]['favicon']['description']);

        $this->updated = [];
        self::assertFalse($this->builder()->build(['generate_images' => false] + self::ARGUMENTS)['favicon']);
        self::assertSame([], $this->updated);
    }
}
