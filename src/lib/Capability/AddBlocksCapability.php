<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Capability;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\IbexaAiCraft\Site\ItemsCreator;
use Masilia\IbexaAiCraft\Support\Conventions;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Throwable;

final class AddBlocksCapability extends AbstractCapability
{
    public function __construct(
        private readonly ItemsCreator $creator,
        private readonly Repository $repository,
        private readonly Conventions $conventions,
    ) {
    }

    public function name(): string
    {
        return 'site.add_blocks';
    }

    public function summary(): string
    {
        return 'Add blocks to a page (or child items to a block), all at once, and link them to it.';
    }

    public function description(): string
    {
        return sprintf(
            'Creates every item in one go and lists them in the relation-list field of the content (link_field, "%s" for a page: its blocks; "%s" for a block: its cards or rows), '
            . 'keeping what the field already lists. An item is {type, fields, items?}: "items" are the child items of that block, created and linked too, so a whole page is one call. '
            . 'type must be one the field accepts (site.explore gives parentBlocksAllowedTypes and the block types with their fields). If one item fails none is kept. '
            . 'The answer has item_ids (the blocks, in order) and children (the items created under each block).',
            $this->conventions->blocksField,
            $this->conventions->itemsField,
        );
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['content_id', 'items'],
            'properties' => [
                'content_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'The page (or block) the items belong to'],
                'link_field' => ['type' => 'string', 'description' => sprintf('The list-of-relations field that receives them (default "%s")', $this->conventions->blocksField)],
                'items' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => ItemsCreator::MAX_ITEMS,
                    'items' => [
                        'type' => 'object',
                        'required' => ['type'],
                        'properties' => [
                            'type' => ['type' => 'string', 'description' => 'Content type identifier of the block, e.g. hero_banner'],
                            'fields' => ['type' => 'object', 'description' => 'Field identifier => value, as for content.create'],
                            'items' => ['type' => 'array', 'description' => 'Child items of this block: the same shape'],
                        ],
                    ],
                ],
                'language' => ['type' => 'string', 'description' => 'Language of the text, as a code ("fre-FR") or its short form ("fr"); default: the language of the page'],
            ],
        ];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['content', 'create'], ['content', 'edit'], ['content', 'publish']];
    }

    public function examples(): array
    {
        return [[
            'description' => 'Two blocks on a page, the second with cards',
            'arguments' => [
                'content_id' => 1353,
                'items' => [
                    ['type' => 'hero_banner', 'fields' => ['title' => 'Welcome', 'subtitle' => 'Energy for all']],
                    ['type' => 'info_cards', 'fields' => ['title' => 'Our work'], 'items' => [['type' => 'card_item', 'fields' => ['title' => 'Solar', 'body' => 'Panels']]]],
                ],
            ],
        ]];
    }

    public function validate(array $arguments): ?string
    {
        return $this->creator->problem((int) $arguments['content_id'], (string) ($arguments['link_field'] ?? $this->conventions->blocksField), (array) $arguments['items'], isset($arguments['language']) ? (string) $arguments['language'] : null);
    }

    public function preview(array $arguments): Preview
    {
        try {
            $name = $this->repository->getContentService()->loadContentInfo((int) $arguments['content_id'])->name;
        } catch (Throwable) {
            $name = '';
        }
        $blocks = array_map(static function (array $item): string {
            $children = isset($item['items']) ? count((array) $item['items']) : 0;

            return $item['type'] . ($children > 0 ? sprintf(' (%d items)', $children) : '');
        }, array_values((array) $arguments['items']));

        return new Preview(
            'Add blocks',
            [
                ['Content', $name !== '' ? sprintf('%s (content %d)', $name, (int) $arguments['content_id']) : (string) (int) $arguments['content_id']],
                ['Field', (string) ($arguments['link_field'] ?? $this->conventions->blocksField)],
                ['Blocks', implode(', ', $blocks)],
            ],
            ['A new version of the content is published.'],
        );
    }

    public function execute(array $arguments): CapabilityResult
    {
        $contentId = (int) $arguments['content_id'];
        $result = $this->creator->create(
            $contentId,
            (string) ($arguments['link_field'] ?? $this->conventions->blocksField),
            array_values((array) $arguments['items']),
            isset($arguments['language']) ? (string) $arguments['language'] : null,
        );

        return CapabilityResult::ok(sprintf('Created %d item(s) under content %d', count($result['item_ids']), $contentId), ['content_id' => $contentId] + $result);
    }
}
