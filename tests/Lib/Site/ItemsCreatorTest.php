<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Tests\Lib\Site;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\LanguageService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Language;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinitionCollection;
use Masilia\IbexaAi\Ibexa\Content\EnabledLanguages;
use Masilia\Contracts\IbexaAi\Content\ContentWriter;
use Masilia\IbexaAiCraft\Site\ItemsCreator;
use Masilia\IbexaAiCraft\Support\Conventions;
use Masilia\IbexaAiCraft\Tests\Lib\Catalog\BlockCatalogFactoryTrait;
use Masilia\IbexaAiCraft\Tests\Lib\Catalog\FakeContentType;
use Masilia\IbexaAiCraft\Tests\Lib\Catalog\FakeFieldDefinition;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ItemsCreatorTest extends TestCase
{
    use BlockCatalogFactoryTrait;

    private int $rollbacks = 0;
    private int $commits = 0;

    private function type(string $identifier, array $fields): FakeContentType
    {
        $definitions = [];
        foreach ($fields as $id => [$type, $settings]) {
            $definitions[] = new FakeFieldDefinition($id, $type, $settings);
        }

        return new FakeContentType($identifier, ucfirst($identifier), new FieldDefinitionCollection($definitions));
    }

    private function creator(?ContentWriter $writer = null): ItemsCreator
    {
        $page = $this->type('page', ['blocks' => ['ibexa_object_relation_list', ['selectionContentTypes' => ['hero_banner', 'info_cards']]], 'title' => ['ibexa_string', []]]);
        $types = [
            'page' => $page,
            'hero_banner' => $this->type('hero_banner', ['title' => ['ibexa_string', []]]),
            'info_cards' => $this->type('info_cards', ['items' => ['ibexa_object_relation_list', ['selectionContentTypes' => ['card_item']]]]),
            'card_item' => $this->type('card_item', ['title' => ['ibexa_string', []]]),
        ];

        $info = $this->createMock(ContentInfo::class);
        $info->method('getContentType')->willReturn($page);
        $info->method('getMainLocation')->willThrowException(new RuntimeException('The content has no location.'));
        $contents = $this->createMock(ContentService::class);
        $contents->method('loadContentInfo')->willReturnCallback(static fn (int $id) => $id === 100 ? $info : throw new \Ibexa\Core\Base\Exceptions\NotFoundException('content', $id));
        $typeService = $this->createMock(ContentTypeService::class);
        $typeService->method('loadContentTypeByIdentifier')->willReturnCallback(static fn (string $id) => $types[$id] ?? throw new \Ibexa\Core\Base\Exceptions\NotFoundException('type', $id));

        $languages = $this->createMock(LanguageService::class);
        $languages->method('loadLanguages')->willReturn([new Language(['languageCode' => 'eng-GB', 'enabled' => true]), new Language(['languageCode' => 'fre-FR', 'enabled' => true]), new Language(['languageCode' => 'ara-SA', 'enabled' => false])]);
        $repository = $this->createMock(Repository::class);
        $repository->method('getContentLanguageService')->willReturn($languages);
        $repository->method('getContentService')->willReturn($contents);
        $repository->method('getContentTypeService')->willReturn($typeService);
        $repository->method('beginTransaction')->willReturnCallback(function (): void {});
        $repository->method('commit')->willReturnCallback(function (): void { ++$this->commits; });
        $repository->method('rollback')->willReturnCallback(function (): void { ++$this->rollbacks; });

        return new ItemsCreator($repository, $writer ?? $this->createMock(ContentWriter::class), new EnabledLanguages($languages), new Conventions());
    }

    public function testAValidRequestHasNoProblem(): void
    {
        $items = [['type' => 'hero_banner', 'fields' => ['title' => 'A']], ['type' => 'info_cards', 'items' => [['type' => 'card_item', 'fields' => ['title' => 'x']]]]];

        self::assertNull($this->creator()->problem(100, 'blocks', $items));
    }

    /**
     * @return iterable<string, array{0: int, 1: string, 2: array<mixed>, 3: string}>
     */
    public static function problems(): iterable
    {
        yield 'a content that does not exist' => [999, 'blocks', [['type' => 'hero_banner']], 'The content 999 does not exist'];
        yield 'no items' => [100, 'blocks', [], 'at least one item'];
        yield 'a field that is not a list of relations' => [100, 'title', [['type' => 'hero_banner']], 'has no list-of-relations field "title" (it has: blocks)'];
        yield 'a type the field does not accept' => [100, 'blocks', [['type' => 'card_item']], 'accepts [hero_banner, info_cards]; item 1 is "card_item"'];
        yield 'an item without a type' => [100, 'blocks', [['fields' => []]], 'Item 1 has no "type"'];
        yield 'fields that are not an object' => [100, 'blocks', [['type' => 'hero_banner', 'fields' => 'x']], 'fields must be an object'];
        yield 'a child the block does not accept' => [100, 'blocks', [['type' => 'info_cards', 'items' => [['type' => 'hero_banner']]]], 'Item 1 ("info_cards"): The field "items" of "info_cards" accepts [card_item]'];
        yield 'too many items' => [100, 'blocks', array_fill(0, ItemsCreator::MAX_ITEMS + 1, ['type' => 'hero_banner']), 'at most 40'];
    }

    /**
     * @dataProvider problems
     *
     * @param array<mixed> $items
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('problems')]
    public function testWhatCannotWorkIsFoundBeforeWritingAnything(int $contentId, string $field, array $items, string $expected): void
    {
        self::assertStringContainsString($expected, (string) $this->creator()->problem($contentId, $field, $items));
    }

    public function testTheLanguageTheModelNamesIsCheckedAndMappedToAnEnabledOne(): void
    {
        $items = [['type' => 'hero_banner']];

        self::assertNull($this->creator()->problem(100, 'blocks', $items, 'fr'), '"fr" is the short form of fre-FR');
        self::assertNull($this->creator()->problem(100, 'blocks', $items, 'fre-FR'));
        self::assertStringContainsString('does not exist or is disabled', (string) $this->creator()->problem(100, 'blocks', $items, 'xx'));
        self::assertStringContainsString('does not exist or is disabled', (string) $this->creator()->problem(100, 'blocks', $items, 'ara-SA'), 'a disabled language is refused');
    }

    public function testAnyFailureUndoesEverythingAndComesOut(): void
    {
        $creator = $this->creator();

        try {
            $creator->create(100, 'blocks', [['type' => 'hero_banner', 'fields' => ['title' => 'A']]]);
            self::fail('The failure should come out.');
        } catch (\Throwable $e) {
            self::assertSame('The content has no location.', $e->getMessage());
        }

        self::assertSame(1, $this->rollbacks);
        self::assertSame(0, $this->commits);
    }
}
