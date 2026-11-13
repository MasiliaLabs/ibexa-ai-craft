<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiCraft\Support;

/**
 * How the sites of a project are built, as the settings of the bundle: the part of the way of working that changes from
 * one project to the next. The defaults are the way the Masilia sites are built; another project sets its own.
 */
final readonly class Conventions
{
    /**
     * @param string       $blockGroup    The group of content types that are blocks (the parts a page is made of)
     * @param list<string> $contentGroups The groups of the other content types the assistant works with
     * @param string       $pageType      The content type of a page
     * @param string       $blocksField   The field of a page that holds its blocks (a list of relations)
     * @param string       $itemsField    The field of a block that holds its child items, when it has some
     */
    public function __construct(
        public string $blockGroup = 'Blocks',
        public array $contentGroups = ['Content', 'Block items', 'Media', 'Taxonomies', 'Configurations'],
        public string $pageType = ContentTypeId::PAGE,
        public string $blocksField = FieldId::BLOCKS,
        public string $itemsField = 'items',
    ) {
    }

    /**
     * @param array<string, mixed> $settings The "conventions" node of the bundle configuration
     */
    public static function fromSettings(array $settings): self
    {
        $defaults = new self();

        return new self(
            blockGroup: (string) ($settings['block_group'] ?? $defaults->blockGroup),
            contentGroups: array_values(array_map('strval', (array) ($settings['content_groups'] ?? $defaults->contentGroups))),
            pageType: (string) ($settings['page_type'] ?? $defaults->pageType),
            blocksField: (string) ($settings['blocks_field'] ?? $defaults->blocksField),
            itemsField: (string) ($settings['items_field'] ?? $defaults->itemsField),
        );
    }
}
