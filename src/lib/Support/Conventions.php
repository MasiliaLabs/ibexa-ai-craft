<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Support;

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
     * @param array<string, string> $itemsFieldByType The field that holds the child items of the block types that do not call it $itemsField ("info_cards" => "cards")
     * @param string       $siteType      The content type of the container of a site (its title, domain, description, favicon)
     * @param string       $homePageType  The content type of the home page of a site (a page of blocks)
     * @param string       $layoutType    The content type of the layout configuration of a site (its back-office title)
     * @param string       $folderType    The content type of a folder (its name)
     * @param string       $blocksFolderSuffix The end of the name of the folder that holds the blocks of a page ("Home Blocks")
     */
    public function __construct(
        public string $blockGroup = 'Blocks',
        public array $contentGroups = ['Content', 'Block items', 'Media', 'Taxonomies', 'Configurations'],
        public string $pageType = 'page',
        public string $blocksField = 'blocks',
        public string $itemsField = 'items',
        public string $siteType = 'site',
        public string $homePageType = 'home_page',
        public string $layoutType = 'layout_config',
        public string $folderType = 'folder',
        public array $itemsFieldByType = [],
        public string $blocksFolderSuffix = 'Blocks',
    ) {
    }

    /** The field that holds the child items of a block of this type */
    public function itemsFieldFor(string $blockType): string
    {
        return $this->itemsFieldByType[$blockType] ?? $this->itemsField;
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
            siteType: (string) ($settings['site_type'] ?? $defaults->siteType),
            homePageType: (string) ($settings['home_page_type'] ?? $defaults->homePageType),
            layoutType: (string) ($settings['layout_type'] ?? $defaults->layoutType),
            folderType: (string) ($settings['folder_type'] ?? $defaults->folderType),
            itemsFieldByType: array_map('strval', (array) ($settings['items_field_by_type'] ?? $defaults->itemsFieldByType)),
            blocksFolderSuffix: (string) ($settings['blocks_folder_suffix'] ?? $defaults->blocksFolderSuffix),
        );
    }
}
