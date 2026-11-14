<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Capability;

use Masilia\IbexaAiCraft\Support\Conventions;
use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Masilia\Contracts\IbexaBlocks\ShownBlocks;

final class PageDescribeCapability extends AbstractCapability
{
    public function __construct(
        private readonly PageBlocks $blocks,
        private readonly Conventions $conventions,
        private readonly ?ShownBlocks $shown = null,
    ) {
    }

    public function name(): string
    {
        return 'page.describe';
    }

    public function summary(): string
    {
        return 'The blocks of a page, in order: their type, their name and how many items each holds.';
    }

    public function description(): string
    {
        return sprintf(
            'Reads the "%s" field of a page: its blocks, from the first to the last, with their content_id, content type, name and the number of items they hold. '
            . 'With a siteaccess, each block also says whether the design of that site can show it (shown). Use it before moving, removing or adding blocks.',
            $this->conventions->blocksField,
        );
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['content_id'], 'properties' => [
            'content_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'The page'],
            'siteaccess' => ['type' => 'string', 'description' => 'The siteaccess of the site, to know whether its design shows each block'],
        ]];
    }

    public function risk(): Risk
    {
        return Risk::READ;
    }

    public function requires(): array
    {
        return [['content', 'read']];
    }

    public function examples(): array
    {
        return [['description' => 'The blocks of the home page', 'arguments' => ['content_id' => 1353]]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $pageId = (int) $arguments['content_id'];
        try {
            $blocks = $this->blocks->describe($pageId);
        } catch (\RuntimeException $e) {
            return CapabilityResult::error($e->getMessage());
        }
        $shown = isset($arguments['siteaccess']) && $this->shown !== null ? $this->shown->forSiteaccess((string) $arguments['siteaccess']) : null;
        if ($shown !== null) {
            $blocks = array_map(static fn (array $block): array => $block + ['shown' => in_array($block['type'], $shown, true)], $blocks);
        }
        $hidden = $shown !== null ? count(array_filter($blocks, static fn (array $block): bool => !$block['shown'])) : 0;

        return CapabilityResult::ok(
            sprintf('"%s" has %d block(s)%s.', $this->blocks->pageName($pageId), count($blocks), $hidden > 0 ? sprintf(', %d of them not shown by this design', $hidden) : ''),
            ['content_id' => $pageId, 'blocks' => $blocks],
        );
    }
}
