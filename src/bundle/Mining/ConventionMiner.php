<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiCraft\Mining;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Masilia\Bundle\IbexaAiCraft\Support\Conventions;
use Throwable;

/**
 * Reads the pages of a site and says how they are built: the blocks they use, in which order, and what is filled in.
 * It is what lets the assistant build a new page like the existing ones instead of from a general idea of a page.
 */
final readonly class ConventionMiner
{
    private const MAX_BLOCKS_PER_PAGE = 40;

    public function __construct(
        private Repository $repository,
        private Conventions $conventions,
    ) {
    }

    /**
     * @return array<string, mixed> See {@see PagePatterns::summarize()}, plus "page_type", "root_location_id" and the block types the page accepts but the sample never uses
     */
    public function mine(int $rootLocationId, int $maxPages = 60): array
    {
        $pages = $this->describePages($rootLocationId, $maxPages);
        $summary = PagePatterns::summarize($pages);
        $used = array_column($summary['block_types'], 'type');

        return $summary + [
            'page_type' => $this->conventions->pageType,
            'root_location_id' => $rootLocationId,
            'accepted_but_unused' => array_values(array_diff($this->acceptedBlockTypes(), $used)),
        ];
    }

    /**
     * @return list<array{title: string, blocks: list<array{type: string, children?: int, filled?: array<string, bool>}>}>
     */
    private function describePages(int $rootLocationId, int $maxPages): array
    {
        $root = $this->repository->getLocationService()->loadLocation($rootLocationId);
        $query = new Query([
            'filter' => new Criterion\LogicalAnd([
                new Criterion\ContentTypeIdentifier($this->conventions->pageType),
                new Criterion\Subtree($root->pathString),
            ]),
            'sortClauses' => [new Query\SortClause\DatePublished(Query::SORT_DESC)],
            'limit' => max(1, min($maxPages, 200)),
        ]);

        $contents = $this->repository->getContentService();
        $fieldTypes = $this->repository->getFieldTypeService();
        $pages = [];
        foreach ($this->repository->getSearchService()->findContentInfo($query)->searchHits as $hit) {
            $info = $hit->valueObject;
            try {
                $page = $contents->loadContent($info->id);
                $ids = $page->getFieldValue($this->conventions->blocksField)->destinationContentIds ?? [];
            } catch (Throwable) {
                continue;
            }

            $blocks = [];
            foreach (array_slice(array_map('intval', (array) $ids), 0, self::MAX_BLOCKS_PER_PAGE) as $blockId) {
                try {
                    $block = $contents->loadContent($blockId);
                } catch (Throwable) {
                    continue; // a block the user may not read, or one that is gone
                }
                $type = $block->getContentType();
                $filled = [];
                foreach ($type->getFieldDefinitions() as $definition) {
                    $value = $block->getFieldValue($definition->identifier);
                    $filled[$definition->identifier] = $value !== null && !$fieldTypes->getFieldType($definition->fieldTypeIdentifier)->isEmptyValue($value);
                }
                $entry = ['type' => $type->identifier, 'filled' => $filled];
                $itemsValue = $block->getFieldValue($this->conventions->itemsField);
                if ($itemsValue !== null && isset($itemsValue->destinationContentIds)) {
                    $entry['children'] = count((array) $itemsValue->destinationContentIds);
                }
                $blocks[] = $entry;
            }
            $pages[] = ['title' => (string) $info->name, 'blocks' => $blocks];
        }

        return $pages;
    }

    /**
     * @return list<string> The block types the blocks field of a page accepts
     */
    private function acceptedBlockTypes(): array
    {
        try {
            $page = $this->repository->getContentTypeService()->loadContentTypeByIdentifier($this->conventions->pageType);

            return array_values((array) ($page->getFieldDefinition($this->conventions->blocksField)?->getFieldSettings()['selectionContentTypes'] ?? []));
        } catch (Throwable) {
            return [];
        }
    }
}
