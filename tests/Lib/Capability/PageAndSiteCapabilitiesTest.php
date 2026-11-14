<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Tests\Lib\Capability;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Core\FieldType\RelationList\Value as RelationListValue;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentTypeGroup;
use Masilia\IbexaAiCraft\Capability\ItemTypesCapability;
use Masilia\IbexaAiCraft\Capability\NewsletterSubscribersCapability;
use Masilia\IbexaAiCraft\Capability\PageBlockMoveCapability;
use Masilia\IbexaAiCraft\Capability\PageBlockRemoveCapability;
use Masilia\IbexaAiCraft\Capability\PageBlocks;
use Masilia\IbexaAiCraft\Capability\PageDescribeCapability;
use Masilia\IbexaAiCraft\Capability\ThemeCheckCapability;
use Masilia\IbexaAiCraft\Capability\ThemeCreateCapability;
use Masilia\IbexaAiCraft\Support\Conventions;
use Masilia\Contracts\IbexaAi\Content\ContentTypeSchemas;
use Masilia\Contracts\IbexaAi\Content\ContentWriter;
use Masilia\Contracts\IbexaAi\Content\Languages;
use Masilia\Contracts\IbexaBlocks\ShownBlocks;
use Masilia\Contracts\IbexaBlocks\ShownViews;
use Masilia\Contracts\IbexaBlocks\Subscribers;
use Masilia\Contracts\IbexaBlocks\Themes;
use PHPUnit\Framework\TestCase;

/** The pages, themes, lists and items of the sites: what each capability reads and writes, with the conventions of the project. */
final class PageAndSiteCapabilitiesTest extends TestCase
{
    private ContentService $contents;
    private Repository $repository;
    private Conventions $conventions;

    protected function setUp(): void
    {
        $this->contents = $this->createMock(ContentService::class);
        $this->repository = $this->createMock(Repository::class);
        $this->repository->method('getContentService')->willReturn($this->contents);
        $this->conventions = new Conventions();
    }

    /** A page (content 1) whose blocks are 10, 11, 12, and the blocks themselves. */
    private function page(): void
    {
        $page = $this->content(1, 'page', 'Home', ['blocks' => [10, 11, 12]]);
        $contents = [1 => $page, 10 => $this->content(10, 'hero_banner', 'Hero', []), 11 => $this->content(11, 'info_cards', 'Cards', ['items' => [100, 101]]), 12 => $this->content(12, 'cta', 'Call', [])];
        $this->contents->method('loadContent')->willReturnCallback(static fn (int $id): Content => $contents[$id] ?? throw new \RuntimeException('no content'));
        $info = $this->createMock(ContentInfo::class);
        $info->method('__get')->willReturn('Home');
        $this->contents->method('loadContentInfo')->willReturn($info);
    }

    /** @param array<string, list<int>> $relations */
    private function content(int $id, string $type, string $name, array $relations): Content
    {
        $contentType = $this->createMock(ContentType::class);
        $contentType->method('getIdentifier')->willReturn($type);
        $contentType->method('hasFieldDefinition')->willReturnCallback(static fn (string $field): bool => isset($relations[$field]) || ($type === 'page' && $field === 'blocks') || ($type === 'info_cards' && $field === 'items'));
        $content = $this->createMock(Content::class);
        $content->method('getContentType')->willReturn($contentType);
        $content->method('getName')->willReturn($name);
        $content->method('getFieldValue')->willReturnCallback(static fn (string $field): RelationListValue => new RelationListValue($relations[$field] ?? []));

        return $content;
    }

    private function blocks(): PageBlocks
    {
        return new PageBlocks($this->repository, $this->conventions);
    }

    public function testAPageIsDescribedBlockByBlockAndAgainstTheDesign(): void
    {
        $this->page();
        $shown = $this->createMock(ShownBlocks::class);
        $shown->method('forSiteaccess')->with('site')->willReturn(['hero_banner', 'info_cards']);

        $result = (new PageDescribeCapability($this->blocks(), $this->conventions, $shown))->execute(['content_id' => 1, 'siteaccess' => 'site']);

        self::assertTrue($result->success);
        self::assertSame([10, 11, 12], array_column($result->data['blocks'], 'content_id'));
        self::assertSame(2, $result->data['blocks'][1]['items']);
        self::assertSame([true, true, false], array_column($result->data['blocks'], 'shown'));
        self::assertStringContainsString('1 of them not shown', $result->message);
    }

    public function testAContentThatIsNotAPageOfBlocksIsAnswered(): void
    {
        $this->page();

        $result = (new PageDescribeCapability($this->blocks(), $this->conventions))->execute(['content_id' => 12]);

        self::assertFalse($result->success);
        self::assertStringContainsString('is not a page of blocks', $result->message);
    }

    public function testABlockMovesToAPositionAndTheOthersKeepTheirOrder(): void
    {
        $this->page();
        $writer = $this->createMock(ContentWriter::class);
        $languages = $this->createMock(Languages::class);
        $languages->method('default')->willReturn('eng-GB');
        $writer->expects(self::once())->method('update')->with(1, ['blocks' => [12, 10, 11]], 'eng-GB');
        $move = new PageBlockMoveCapability($this->blocks(), $writer, $languages, $this->conventions);

        self::assertNull($move->validate(['content_id' => 1, 'block_content_id' => 12, 'position' => 0]));
        self::assertTrue($move->execute(['content_id' => 1, 'block_content_id' => 12, 'position' => 0])->success);
        self::assertStringContainsString('is not on this page', (string) $move->validate(['content_id' => 1, 'block_content_id' => 99, 'position' => 0]));
        self::assertStringContainsString('last position is 2', (string) $move->validate(['content_id' => 1, 'block_content_id' => 10, 'position' => 5]));
    }

    public function testABlockIsTakenOffThePageAndKept(): void
    {
        $this->page();
        $writer = $this->createMock(ContentWriter::class);
        $languages = $this->createMock(Languages::class);
        $languages->method('default')->willReturn('eng-GB');
        $writer->expects(self::once())->method('update')->with(1, ['blocks' => [10, 12]], 'eng-GB');
        $remove = new PageBlockRemoveCapability($this->blocks(), $writer, $languages, $this->conventions);

        self::assertTrue($remove->execute(['content_id' => 1, 'block_content_id' => 11])->success);
        self::assertStringContainsString('the block itself is kept', implode(' ', $remove->preview(['content_id' => 1, 'block_content_id' => 11])->notes));
        self::assertStringContainsString('is not on this page', (string) $remove->validate(['content_id' => 1, 'block_content_id' => 5]));
    }

    public function testThemesAreCreatedOnlyWhenTheProjectAllowsIt(): void
    {
        $themes = $this->createMock(Themes::class);
        $themes->expects(self::once())->method('create')->with('blog', 'standard')->willReturn(['files' => ['templates/themes/blog/theme.yaml'], 'next' => ['Build']]);

        self::assertStringContainsString('turned off', (string) (new ThemeCreateCapability($themes))->validate(['name' => 'blog']));
        $on = new ThemeCreateCapability($themes, true);
        self::assertNull($on->validate(['name' => 'blog']));
        self::assertTrue($on->execute(['name' => 'blog'])->success);
    }

    public function testADesignThatDoesNotExistIsRefusedBeforeItIsChecked(): void
    {
        $themes = $this->createMock(Themes::class);
        $themes->method('designs')->willReturn(['fossil_exit' => ['fossil_exit', 'standard']]);
        $themes->method('check')->willReturn([['level' => 'error', 'theme' => null, 'message' => 'x'], ['level' => 'notice', 'theme' => 'standard', 'message' => 'y']]);
        $check = new ThemeCheckCapability($themes);

        self::assertStringContainsString('no design "nope"', (string) $check->validate(['design' => 'nope']));
        self::assertNull($check->validate(['design' => 'fossil_exit']));
        self::assertSame('1 error(s), 1 notice(s).', $check->execute(['design' => 'fossil_exit'])->message);
    }

    public function testTheNewsletterIsReadOnlyWhenItIsTurnedOnAndAsPersonalData(): void
    {
        self::assertSame('The newsletter is not turned on for this project.', (new NewsletterSubscribersCapability())->validate([]));
        $subscribers = $this->createMock(Subscribers::class);
        $subscribers->method('count')->willReturn(2);
        $subscribers->method('latest')->with(5)->willReturn([['email' => 'a@example.org', 'name' => null, 'confirmed' => true, 'active' => true, 'subscribed_at' => '2026-11-01T10:00:00+00:00']]);
        $capability = new NewsletterSubscribersCapability($subscribers);

        self::assertNull($capability->validate([]));
        self::assertSame(2, $capability->execute(['limit' => 5])->data['count']);
        self::assertSame(\Masilia\Contracts\IbexaAi\Os\Risk::ACCESS, $capability->risk());
    }

    public function testTheItemsASiteListsAreItsCardsThatAreNotBlocks(): void
    {
        $group = $this->createMock(ContentTypeGroup::class);
        $block = $this->createMock(ContentType::class);
        $block->identifier = 'hero_banner';
        $types = $this->createMock(ContentTypeService::class);
        $types->method('loadContentTypeGroupByIdentifier')->willReturn($group);
        $types->method('loadContentTypes')->willReturn([$block]);
        $views = $this->createMock(ShownViews::class);
        $views->method('contentTypes')->with('site', 'block_item')->willReturn(['article', 'hero_banner', 'ghost']);
        $schemas = $this->createMock(ContentTypeSchemas::class);
        $schemas->method('schema')->willReturnCallback(static fn (string $id): ?array => $id === 'article' ? ['identifier' => 'article', 'name' => 'Article', 'fields' => ['title' => ['type' => 'ibexa_string', 'required' => true], 'body' => ['type' => 'ibexa_richtext', 'required' => false]]] : null);

        $result = (new ItemTypesCapability($views, $types, $schemas, $this->conventions))->execute(['siteaccess' => 'site']);

        self::assertCount(1, $result->data['items']);
        self::assertSame('article', $result->data['items'][0]['type']);
        self::assertSame(['title'], $result->data['items'][0]['required_fields']);
    }
}
