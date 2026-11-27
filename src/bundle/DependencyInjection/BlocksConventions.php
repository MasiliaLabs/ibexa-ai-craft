<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiCraft\DependencyInjection;

use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use Masilia\IbexaAiCraft\Support\Conventions;
use Masilia\IbexaAiCraft\Support\ConventionsSource;

/**
 * How a site is built is defined once: when masilia/ibexa-blocks is installed, its conventions for the site of the request
 * are the ones the assistant follows, so that it builds pages the way the front office renders them. Without it, the settings
 * of this bundle are used. Asked each time: the site is that of the request.
 */
final readonly class BlocksConventions implements ConventionsSource
{
    private const SHARED = ['page_type', 'blocks_field', 'items_field', 'items_field_by_type', 'block_group', 'content_groups', 'blocks_folder_suffix'];

    /**
     * @param array<string, mixed> $own The "conventions" node of this bundle
     */
    public function __construct(private array $own, private ?SettingsProvider $blocks = null)
    {
    }

    public function conventions(): Conventions
    {
        if ($this->blocks === null) {
            return Conventions::fromSettings($this->own);
        }

        $c = $this->blocks->conventions();
        $shared = [
            'page_type' => $c->pageType, 'blocks_field' => $c->blocksField, 'items_field' => $c->itemsField,
            'items_field_by_type' => $c->itemsFieldByType, 'block_group' => $c->blockGroup, 'content_groups' => $c->contentGroups,
            'blocks_folder_suffix' => $c->blocksFolderSuffix,
        ];

        return Conventions::fromSettings(array_replace($this->own, array_intersect_key($shared, array_flip(self::SHARED))));
    }
}
