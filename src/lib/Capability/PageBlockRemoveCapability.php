<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Capability;

use Masilia\IbexaAiCraft\Support\Conventions;
use Masilia\Contracts\IbexaAi\Content\ContentWriter;
use Masilia\Contracts\IbexaAi\Content\Languages;
use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class PageBlockRemoveCapability extends AbstractCapability
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
        return 'page.block.remove';
    }

    public function summary(): string
    {
        return 'Take a block off a page (the block itself is kept).';
    }

    public function description(): string
    {
        return sprintf(
            'Removes the block from the "%s" field of the page, so that the page no longer shows it, and publishes a new version of the page. The block content is not deleted: '
            . 'trash it too with content.trash (in one propose_changes) if it is not used anywhere else.',
            $this->conventions->blocksField,
        );
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['content_id', 'block_content_id'], 'properties' => [
            'content_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'The page'],
            'block_content_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'The block to take off, as page.describe gives it'],
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
        return [['description' => 'Take a block off the home page', 'arguments' => ['content_id' => 1353, 'block_content_id' => 1360]]];
    }

    public function validate(array $arguments): ?string
    {
        try {
            $this->remaining($arguments);
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Take a block off a page', [
            ['Page', sprintf('%s (content %d)', $this->blocks->pageName((int) $arguments['content_id']), (int) $arguments['content_id'])],
            ['Block', (string) (int) $arguments['block_content_id']],
        ], ['The page stops showing the block; the block itself is kept.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $remaining = $this->remaining($arguments);
        $this->writer->update((int) $arguments['content_id'], [$this->conventions->blocksField => $remaining], $this->languages->default());

        return CapabilityResult::ok(sprintf('Block %d is no longer on the page', (int) $arguments['block_content_id']), ['blocks' => $remaining]);
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return list<int>
     */
    private function remaining(array $arguments): array
    {
        $ids = $this->blocks->ids((int) $arguments['content_id']);
        $block = (int) $arguments['block_content_id'];
        if (!in_array($block, $ids, true)) {
            throw new \RuntimeException(sprintf('The block %d is not on this page: see page.describe.', $block));
        }

        return array_values(array_filter($ids, static fn (int $id): bool => $id !== $block));
    }
}
