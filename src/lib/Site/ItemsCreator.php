<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Site;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Masilia\IbexaAiCraft\Support\Conventions;
use Masilia\IbexaAiCraft\Support\ConventionsSource;
use Masilia\Contracts\IbexaAi\Content\ContentWriter;
use Masilia\Contracts\IbexaAi\Content\Languages;
use Throwable;

/**
 * Creates the items a content is made of (the blocks of a page, the cards of a block) and links them to it.
 *
 * The items are created in a folder next to the content, then listed in a relation-list field of the content, which
 * gets a new version. An item can bring items of its own ("items" in its definition): they go in the field of the
 * item that holds child items. Everything is done in one transaction: a failing item undoes the others.
 */
final readonly class ItemsCreator
{
    public const MAX_ITEMS = 40;
    private const MAX_DEPTH = 3;
    private const RELATION_LIST = 'ibexa_object_relation_list';
    /** The field of a folder that holds its name. */
    private const FOLDER_NAME = 'name';

    public function __construct(
        private Repository $repository,
        private ContentWriter $writer,
        private Languages $languages,
        private ConventionsSource $conventionsSource,
    ) {
    }

    /**
     * What is wrong with the request, without writing anything: the content, the field, the types of the items.
     *
     * @param list<array<string, mixed>> $items
     */
    public function problem(int $contentId, string $linkField, array $items, ?string $language = null): ?string
    {
        if ($language !== null && $this->language($language) === null) {
            return sprintf('The language "%s" does not exist or is disabled (use a code like "fre-FR", or "fr"): see localization.language.list.', $language);
        }

        try {
            $parentType = $this->repository->getContentService()->loadContentInfo($contentId)->getContentType();
        } catch (Throwable) {
            return sprintf('The content %d does not exist.', $contentId);
        }

        if ($this->holdsPageBlocks($parentType->identifier, $linkField) && !$parentType->isContainer()) {
            return sprintf('The content type "%s" is not a container, so the folder of its blocks cannot go under it: make it one (masilia:blocks:model:install --allow-container with masilia/ibexa-blocks).', $parentType->identifier);
        }

        return $this->itemsProblem($parentType, $linkField, $items, 1);
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return array{item_ids: list<int>, children: array<int, list<int>>}
     */
    public function create(int $contentId, string $linkField, array $items, ?string $language = null): array
    {
        $this->repository->beginTransaction();
        try {
            $children = [];
            $ids = $this->createUnder($contentId, $linkField, $items, $language !== null ? $this->language($language) : null, $children);
            $this->repository->commit();
        } catch (Throwable $e) {
            $this->repository->rollback();
            throw $e;
        }

        return ['item_ids' => $ids, 'children' => $children];
    }

    /**
     * The language the model names ("fr", "fre-FR") as the code of an enabled language of the installation, or null.
     */
    private function language(string $language): ?string
    {
        return $this->languages->resolve($language);
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function itemsProblem(ContentType $parentType, string $linkField, array $items, int $depth): ?string
    {
        if ($items === [] || !array_is_list($items)) {
            return 'items must be a list of at least one item: {type, fields, items?}.';
        }
        if (count($items) > self::MAX_ITEMS) {
            return sprintf('There are %d items: at most %d at once.', count($items), self::MAX_ITEMS);
        }

        $field = $this->relationField($parentType, $linkField);
        if ($field === null) {
            $lists = array_map(static fn (FieldDefinition $f): string => $f->identifier, array_filter(
                iterator_to_array($parentType->getFieldDefinitions(), false),
                static fn (FieldDefinition $f): bool => $f->fieldTypeIdentifier === self::RELATION_LIST,
            ));

            return sprintf('"%s" has no list-of-relations field "%s" (it has: %s).', $parentType->identifier, $linkField, $lists === [] ? 'none' : implode(', ', $lists));
        }
        $allowed = array_values((array) ($field->getFieldSettings()['selectionContentTypes'] ?? []));

        foreach ($items as $index => $item) {
            $type = is_array($item) ? (string) ($item['type'] ?? '') : '';
            if ($type === '') {
                return sprintf('Item %d has no "type".', $index + 1);
            }
            if (isset($item['fields']) && !is_array($item['fields'])) {
                return sprintf('Item %d ("%s"): fields must be an object.', $index + 1, $type);
            }
            if ($allowed !== [] && !in_array($type, $allowed, true)) {
                return sprintf('The field "%s" of "%s" accepts [%s]; item %d is "%s".', $linkField, $parentType->identifier, implode(', ', $allowed), $index + 1, $type);
            }
            try {
                $itemType = $this->repository->getContentTypeService()->loadContentTypeByIdentifier($type);
            } catch (Throwable) {
                return sprintf('Item %d: there is no content type "%s".', $index + 1, $type);
            }
            if (isset($item['items'])) {
                if ($depth >= self::MAX_DEPTH) {
                    return sprintf('Items can be nested %d levels deep at most.', self::MAX_DEPTH);
                }
                $problem = $this->itemsProblem($itemType, $this->conventionsSource->conventions()->itemsFieldFor($type), (array) $item['items'], $depth + 1);
                if ($problem !== null) {
                    return sprintf('Item %d ("%s"): %s', $index + 1, $type, $problem);
                }
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>>   $items
     * @param array<int, list<int>>        $children Filled with the items created under each item
     *
     * @return list<int>
     */
    private function createUnder(int $contentId, string $linkField, array $items, ?string $language, array &$children): array
    {
        $contents = $this->repository->getContentService();
        $info = $contents->loadContentInfo($contentId);
        $lang = $language ?? $info->mainLanguageCode;

        $folder = $this->holdsPageBlocks($info->getContentType()->identifier, $linkField)
            ? (new BlocksFolders($this->repository, $this->conventionsSource))->ensure($contentId, $lang)
            : $this->folderUnder($info->getMainLocation()->id, trim(($info->name ?? 'items') . ' items') ?: 'items', $lang);

        $ids = [];
        foreach ($items as $item) {
            $type = (string) $item['type'];
            $itemId = (int) $this->writer->create($type, [$folder], (array) ($item['fields'] ?? []), $lang)->content->getId();
            $ids[] = $itemId;
            if (isset($item['items'])) {
                $children[$itemId] = $this->createUnder($itemId, $this->conventionsSource->conventions()->itemsFieldFor($type), (array) $item['items'], $lang, $children);
            }
        }

        $this->link($contentId, $linkField, $ids, $lang);

        return $ids;
    }

    /** The blocks of a page or of the home page go in the folder of its blocks; the items of a block keep their own folder. */
    private function holdsPageBlocks(string $contentType, string $linkField): bool
    {
        return $linkField === $this->conventionsSource->conventions()->blocksField && in_array($contentType, [$this->conventionsSource->conventions()->pageType, $this->conventionsSource->conventions()->homePageType], true);
    }

    /**
     * The folder the items go in: the one next to the content with that name, or a new one.
     */
    private function folderUnder(int $parentLocationId, string $name, string $language): int
    {
        $locations = $this->repository->getLocationService();
        foreach ($locations->loadLocationChildren($locations->loadLocation($parentLocationId), 0, 100) as $child) {
            if ($child->contentInfo->getContentType()->identifier === $this->conventionsSource->conventions()->folderType && strcasecmp((string) $child->getContent()->getName(), $name) === 0) {
                return $child->id;
            }
        }

        $contents = $this->repository->getContentService();
        $struct = $contents->newContentCreateStruct($this->repository->getContentTypeService()->loadContentTypeByIdentifier($this->conventionsSource->conventions()->folderType), $language);
        $struct->setField(self::FOLDER_NAME, $name, $language);
        $draft = $contents->createContent($struct, [$locations->newLocationCreateStruct($parentLocationId)]);

        return $contents->publishVersion($draft->getVersionInfo())->contentInfo->getMainLocation()->id;
    }

    /**
     * Adds the items to the relation-list field of the content (what it already lists stays), in a new version.
     *
     * @param list<int> $itemIds
     */
    private function link(int $contentId, string $linkField, array $itemIds, string $language): void
    {
        $contents = $this->repository->getContentService();
        $info = $contents->loadContentInfo($contentId);
        $current = $contents->loadContent($contentId)->getFieldValue($linkField);
        $existing = $current !== null && isset($current->destinationContentIds) ? array_map('intval', (array) $current->destinationContentIds) : [];

        $draft = $contents->createContentDraft($info);
        $struct = $contents->newContentUpdateStruct();
        $struct->initialLanguageCode = $language;
        $struct->setField($linkField, array_values(array_unique(array_merge($existing, $itemIds))), $language);
        $contents->updateContent($draft->getVersionInfo(), $struct);
        $contents->publishVersion($draft->getVersionInfo());
    }

    private function relationField(ContentType $type, string $identifier): ?FieldDefinition
    {
        foreach ($type->getFieldDefinitions() as $field) {
            if ($field->identifier === $identifier && $field->fieldTypeIdentifier === self::RELATION_LIST) {
                return $field;
            }
        }

        return null;
    }
}
