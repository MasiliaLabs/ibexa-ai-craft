<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiCraft\Capability;

use Masilia\Bundle\IbexaAiCraft\Support\Conventions;
use Masilia\Contracts\IbexaAi\Content\ContentWriter;
use Masilia\Contracts\IbexaAi\Content\Languages;
use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class PageBlockMoveCapability extends AbstractCapability
{
    public function __construct(
        private readonly PageBlocks $blocks,
        private readonly ContentWriter $writer,
        private readonly Languages $languages,
        private readonly Conventions $conventions,
    ) {
    }

    public function name(): string
    {
        return 'page.block.move';
    }

    public function summary(): string
    {
        return 'Move a block to another place in its page.';
    }

    public function description(): string
    {
        return sprintf('Changes the order of the "%s" field of the page: the block goes to the given position (0 is the first block). The other blocks keep their order. A new version of the page is published.', $this->conventions->blocksField);
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['content_id', 'block_content_id', 'position'], 'properties' => [
            'content_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'The page'],
            'block_content_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'The block to move, as page.describe gives it'],
            'position' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Where it goes: 0 is the first'],
        ]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['content', 'edit'], ['content', 'publish']];
    }

    public function examples(): array
    {
        return [['description' => 'Make a block the first of the page', 'arguments' => ['content_id' => 1353, 'block_content_id' => 1360, 'position' => 0]]];
    }

    public function validate(array $arguments): ?string
    {
        try {
            $this->reordered($arguments);
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Move a block', [
            ['Page', sprintf('%s (content %d)', $this->blocks->pageName((int) $arguments['content_id']), (int) $arguments['content_id'])],
            ['Block', (string) (int) $arguments['block_content_id']],
            ['To position', (string) (int) $arguments['position']],
        ], ['A new version of the page is published.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $order = $this->reordered($arguments);
        $this->writer->update((int) $arguments['content_id'], [$this->conventions->blocksField => $order], $this->languages->default());

        return CapabilityResult::ok(sprintf('Block %d is now at position %d', (int) $arguments['block_content_id'], array_search((int) $arguments['block_content_id'], $order, true)), ['blocks' => $order]);
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return list<int>
     */
    private function reordered(array $arguments): array
    {
        $ids = $this->blocks->ids((int) $arguments['content_id']);
        $block = (int) $arguments['block_content_id'];
        if (!in_array($block, $ids, true)) {
            throw new \RuntimeException(sprintf('The block %d is not on this page: see page.describe.', $block));
        }
        $others = array_values(array_filter($ids, static fn (int $id): bool => $id !== $block));
        $position = (int) $arguments['position'];
        if ($position > count($others)) {
            throw new \RuntimeException(sprintf('The page has %d blocks: the last position is %d.', count($ids), count($ids) - 1));
        }
        array_splice($others, $position, 0, [$block]);

        return $others;
    }
}
