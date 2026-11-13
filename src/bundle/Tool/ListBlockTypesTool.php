<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiCraft\Tool;

use Masilia\Bundle\IbexaAiCraft\Catalog\BlockCatalog;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;

/**
 * Exposes the block type catalog to the LLM.
 *
 * Returns all available block types with their fields and capabilities.
 */
final readonly class ListBlockTypesTool implements ToolInterface
{
    public function __construct(
        private BlockCatalog $blockCatalog,
    ) {
    }

    public function getName(): string
    {
        return CraftToolName::LIST_BLOCKS;
    }

    public function getDescription(): string
    {
        return 'List all available block types with their fields and capabilities. Use this to see what blocks can be added to a page.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
        ];
    }

    public function execute(array $params): ToolResult
    {
        $blocks = $this->blockCatalog->getAvailableBlocks();

        return ToolResult::ok(
            sprintf('Found %d block types', count($blocks)),
            ['blocks' => $blocks],
        );
    }
}
