<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Capability;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Masilia\IbexaAiCraft\Support\Conventions;
use Masilia\Contracts\IbexaAi\Content\ContentTypeSchemas;
use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Masilia\Contracts\IbexaBlocks\ShownViews;

final class ItemTypesCapability extends AbstractCapability
{
    public function __construct(
        private readonly ShownViews $views,
        private readonly ContentTypeService $types,
        private readonly ContentTypeSchemas $schemas,
        private readonly Conventions $conventions,
    ) {
    }

    public function name(): string
    {
        return 'item.types';
    }

    public function summary(): string
    {
        return 'The kinds of content a site can show as a card in its lists (articles, events, partners…), with their fields.';
    }

    public function description(): string
    {
        return 'Lists the content types that are not blocks and that the design of the siteaccess has a card for, so that what is created with content.create shows in the grids and sliders. '
            . 'Each comes with its fields and which are required. Create an item with content.create (or as part of propose_changes), under the folder where the site keeps that kind of content.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['siteaccess'], 'properties' => ['siteaccess' => ['type' => 'string', 'description' => 'The siteaccess of the site (site.explore lists them)']]];
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
        return [['description' => 'What the site can list as cards', 'arguments' => ['siteaccess' => 'fossil_exit']]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $blocks = [];
        try {
            foreach ($this->types->loadContentTypes($this->types->loadContentTypeGroupByIdentifier($this->conventions->blockGroup)) as $type) {
                $blocks[$type->identifier] = true;
            }
        } catch (\Throwable) {
            // No group of blocks: every type shown as a card counts.
        }
        $items = [];
        foreach ($this->views->contentTypes((string) $arguments['siteaccess'], 'block_item') as $identifier) {
            if (isset($blocks[$identifier])) {
                continue;
            }
            $schema = $this->schemas->schema($identifier);
            if ($schema === null) {
                continue;
            }
            $items[] = [
                'type' => $identifier,
                'name' => $schema['name'],
                'required_fields' => array_keys(array_filter($schema['fields'], static fn (array $field): bool => $field['required'] ?? false)),
                'fields' => array_map(static fn (array $field): string => (string) $field['type'], $schema['fields']),
            ];
        }

        return CapabilityResult::ok(sprintf('%d kind(s) of item.', count($items)), ['items' => $items]);
    }
}
