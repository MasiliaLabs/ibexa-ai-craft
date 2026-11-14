<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiCraft\Capability;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Bundle\IbexaAiCraft\Support\Conventions;
use Throwable;

/** Reads the blocks of a page, in the order they are shown: the relations of its blocks field. */
readonly class PageBlocks
{
    public function __construct(
        private Repository $repository,
        private Conventions $conventions,
    ) {
    }

    /**
     * @return list<int> The content ids of the blocks, in order
     *
     * @throws \RuntimeException When the content does not exist or has no blocks field
     */
    public function ids(int $pageId): array
    {
        try {
            $page = $this->repository->getContentService()->loadContent($pageId);
        } catch (Throwable) {
            throw new \RuntimeException(sprintf('The content %d does not exist.', $pageId));
        }
        if (!$page->getContentType()->hasFieldDefinition($this->conventions->blocksField)) {
            throw new \RuntimeException(sprintf('"%s" (content %d) has no "%s" field: it is not a page of blocks.', $page->getName(), $pageId, $this->conventions->blocksField));
        }
        $value = $page->getFieldValue($this->conventions->blocksField);

        return array_values(array_map('intval', is_object($value) && property_exists($value, 'destinationContentIds') ? $value->destinationContentIds : []));
    }

    /**
     * @return list<array{position: int, content_id: int, type: string, name: string, items: int|null}>
     */
    public function describe(int $pageId): array
    {
        $contents = $this->repository->getContentService();
        $blocks = [];
        foreach ($this->ids($pageId) as $position => $blockId) {
            try {
                $block = $contents->loadContent($blockId);
                $items = $block->getContentType()->hasFieldDefinition($this->conventions->itemsField) ? $this->count($block->getFieldValue($this->conventions->itemsField)) : null;
                $blocks[] = ['position' => $position, 'content_id' => $blockId, 'type' => $block->getContentType()->getIdentifier(), 'name' => $block->getName(), 'items' => $items];
            } catch (Throwable) {
                $blocks[] = ['position' => $position, 'content_id' => $blockId, 'type' => '?', 'name' => '(not readable)', 'items' => null];
            }
        }

        return $blocks;
    }

    public function pageName(int $pageId): string
    {
        try {
            return $this->repository->getContentService()->loadContentInfo($pageId)->name;
        } catch (Throwable) {
            return '';
        }
    }

    private function count(mixed $value): int
    {
        return is_object($value) && property_exists($value, 'destinationContentIds') ? count($value->destinationContentIds) : 0;
    }
}
