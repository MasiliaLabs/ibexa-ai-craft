<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiCraft\Selftest;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Contracts\IbexaAi\Os\Selftest\SelftestContext;
use Masilia\Contracts\IbexaAi\Os\Selftest\SelftestScenario;
use Masilia\Bundle\IbexaAiCraft\Support\Conventions;
use Throwable;

/**
 * Builds a page of blocks the way the assistant does, on whatever block types the page accepts here, and checks that
 * what must be refused is: a type the page does not take, an unknown language, a field that is not a list.
 * It deletes the page (and with it its blocks) at the end.
 */
final readonly class CraftSelftestScenario implements SelftestScenario
{
    public function __construct(
        private Repository $repository,
        private Conventions $conventions,
    ) {
    }

    public function name(): string
    {
        return 'craft (pages of blocks)';
    }

    public function run(SelftestContext $context): void
    {
        $types = $this->repository->getContentTypeService();
        try {
            $page = $types->loadContentTypeByIdentifier($this->conventions->pageType);
        } catch (Throwable) {
            $context->skip(sprintf('%s: there is no content type "%s"', $this->name(), $this->conventions->pageType));

            return;
        }
        $blocks = $page->getFieldDefinition($this->conventions->blocksField);
        $allowed = array_values((array) ($blocks?->getFieldSettings()['selectionContentTypes'] ?? []));
        $pageFields = RequiredFields::for($page, 'Selftest ' . $context->stamp);
        $blockType = $this->fillableBlock($allowed, $context->stamp);
        if ($blocks === null || $pageFields === null || $blockType === null) {
            $context->skip(sprintf('%s: the page needs a field "%s" and a block type with plain required fields', $this->name(), $this->conventions->blocksField));

            return;
        }
        [$blockIdentifier, $blockFields] = $blockType;

        $context->step('explore: the sites are listed', 'site.explore', [], static fn (array $d): ?string => ($d['siteaccesses'] ?? []) !== [] ? null : 'no siteaccess');

        $context->step('create a page', 'content.create', [
            'content_type' => $this->conventions->pageType,
            'parent_location_id' => $context->parentLocationId,
            'fields' => $pageFields,
        ], null, 'page');
        $pageId = (int) ($context->answer('page')['content_id'] ?? 0);
        if ($pageId === 0) {
            return;
        }
        $context->track($pageId);

        $context->step('add two blocks to it', 'site.add_blocks', [
            'content_id' => $pageId,
            'items' => [['type' => $blockIdentifier, 'fields' => $blockFields], ['type' => $blockIdentifier, 'fields' => $blockFields]],
        ], static fn (array $d): ?string => count($d['item_ids'] ?? []) === 2 ? null : 'expected 2 blocks', 'blocks');
        $context->step('the page lists its two blocks', 'relations.list', ['content_id' => $pageId], static fn (array $d): ?string => ($d['outgoing_total'] ?? 0) >= 2 ? null : 'the blocks are not linked');
        $context->step('add one more: what was there stays', 'site.add_blocks', [
            'content_id' => $pageId,
            'items' => [['type' => $blockIdentifier, 'fields' => $blockFields]],
        ]);
        $context->step('the page lists three', 'relations.list', ['content_id' => $pageId], static fn (array $d): ?string => ($d['outgoing_total'] ?? 0) >= 3 ? null : 'a block was lost');

        $context->step('the conventions of the pages are mined', 'site.conventions', ['location_id' => $context->parentLocationId], static fn (array $d): ?string => ($d['pages'] ?? 0) >= 1 && ($d['block_types'] ?? []) !== [] ? null : 'the page was not read', 'conventions');

        if ($allowed !== []) {
            $refused = $this->typeNotIn($allowed);
            $context->step('a type the page does not take is refused', 'site.add_blocks', ['content_id' => $pageId, 'items' => [['type' => $refused]]], null, null, true);
        }
        $context->step('an unknown language is refused', 'site.add_blocks', ['content_id' => $pageId, 'items' => [['type' => $blockIdentifier, 'fields' => $blockFields]], 'language' => 'xx'], null, null, true);
        $context->step('a field that is not a list is refused', 'site.add_blocks', ['content_id' => $pageId, 'link_field' => 'nope_field', 'items' => [['type' => $blockIdentifier]]], null, null, true);
        $context->step('an empty list of blocks is refused', 'site.add_blocks', ['content_id' => $pageId, 'items' => []], null, null, true);
    }

    public function cleanup(): void
    {
        // The page is a tracked content: the selftest deletes it, and with it its blocks.
    }

    /**
     * The first block type the page takes whose required fields can be filled with plain values.
     *
     * @param list<string> $allowed The types the blocks field accepts (empty: any type)
     *
     * @return array{0: string, 1: array<string, mixed>}|null
     */
    private function fillableBlock(array $allowed, string $stamp): ?array
    {
        $types = $this->repository->getContentTypeService();
        $candidates = $allowed;
        if ($candidates === []) {
            try {
                $group = $types->loadContentTypeGroupByIdentifier($this->conventions->blockGroup);
                foreach ($types->loadContentTypes($group) as $type) {
                    $candidates[] = $type->identifier;
                }
            } catch (Throwable) {
                return null;
            }
        }

        foreach ($candidates as $identifier) {
            try {
                $type = $types->loadContentTypeByIdentifier($identifier);
            } catch (Throwable) {
                continue;
            }
            $fields = RequiredFields::for($type, 'Selftest ' . $stamp);
            if ($fields !== null) {
                return [$identifier, $fields];
            }
        }

        return null;
    }

    /**
     * A content type that exists and is not in the list.
     *
     * @param list<string> $allowed
     */
    private function typeNotIn(array $allowed): string
    {
        foreach (['folder', 'user', 'image', 'article'] as $identifier) {
            if (!in_array($identifier, $allowed, true)) {
                return $identifier;
            }
        }

        return 'not_a_type';
    }
}
