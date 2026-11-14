<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Capability;

use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Masilia\Contracts\IbexaBlocks\ListPreview;

final class ListPreviewCapability extends AbstractCapability
{
    public function __construct(private readonly ListPreview $lists)
    {
    }

    public function name(): string
    {
        return 'list.preview';
    }

    public function summary(): string
    {
        return 'What a list block that fills itself shows now: the latest contents of a type.';
    }

    public function description(): string
    {
        return 'Gives the latest visible contents of a content type, in the order the front office lists them in a grid or a slider that fills itself. '
            . 'Use it to check what a list block will show before or after adding items.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['content_type'], 'properties' => [
            'content_type' => ['type' => 'string', 'description' => 'The content type the list shows, e.g. article'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'description' => 'How many (default 6)'],
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
        return [['description' => 'What the list of articles shows', 'arguments' => ['content_type' => 'article', 'limit' => 6]]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $items = $this->lists->of((string) $arguments['content_type'], (int) ($arguments['limit'] ?? 6));

        return CapabilityResult::ok(sprintf('%d item(s).', count($items)), ['items' => $items]);
    }
}
