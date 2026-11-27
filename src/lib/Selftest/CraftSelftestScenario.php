<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Selftest;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Contracts\IbexaAi\Os\Selftest\SelftestContext;
use Masilia\Contracts\IbexaAi\Os\Selftest\SelftestScenario;
use Masilia\Contracts\IbexaSites\SiteRegistry;
use Masilia\IbexaAiCraft\Support\Conventions;
use Masilia\IbexaAiCraft\Support\ConventionsSource;
use Throwable;

/**
 * Builds a page of blocks the way the assistant does, on whatever block types the page accepts here, and checks that
 * what must be refused is: a type the page does not take, an unknown language, a field that is not a list.
 * It deletes the page (and with it its blocks) at the end.
 */
final class CraftSelftestScenario implements SelftestScenario
{
    /** The configuration made in the registry by the run: deleted at the end, whatever happened. */
    private ?string $configured = null;

    public function __construct(
        private readonly Repository $repository,
        private readonly ConventionsSource $conventionsSource,
        private readonly SiteRegistry $registry,
    ) {
    }

    public function name(): string
    {
        return 'craft (pages of blocks)';
    }

    public function run(SelftestContext $context): void
    {
        $types = $this->repository->getContentTypeService();
        try {
            $page = $types->loadContentTypeByIdentifier($this->conventionsSource->conventions()->pageType);
        } catch (Throwable) {
            $context->skip(sprintf('%s: there is no content type "%s"', $this->name(), $this->conventionsSource->conventions()->pageType));

            return;
        }
        $blocks = $page->getFieldDefinition($this->conventionsSource->conventions()->blocksField);
        $allowed = array_values((array) ($blocks?->getFieldSettings()['selectionContentTypes'] ?? []));
        $pageFields = RequiredFields::for($page, 'Selftest ' . $context->stamp);
        $blockType = $this->fillableBlock($allowed, $context->stamp);
        if ($blocks === null || $pageFields === null || $blockType === null) {
            $context->skip(sprintf('%s: the page needs a field "%s" and a block type with plain required fields', $this->name(), $this->conventionsSource->conventions()->blocksField));

            return;
        }
        [$blockIdentifier, $blockFields] = $blockType;

        $context->step('explore: the sites are listed', 'site.explore', [], static fn (array $d): ?string => ($d['siteaccesses'] ?? []) !== [] ? null : 'no siteaccess');

        $context->step('create a page', 'content.create', [
            'content_type' => $this->conventionsSource->conventions()->pageType,
            'parent_location_id' => $context->parentLocationId,
            'fields' => $pageFields,
        ], null, 'page');
        $pageId = (int) ($context->answer('page')['content_id'] ?? 0);
        if ($pageId === 0) {
            return;
        }
        $context->track($pageId);

        $context->step('add two blocks to it', 'site.add_blocks', [
            'content_id' => $pageId,
            'items' => [['type' => $blockIdentifier, 'fields' => $blockFields], ['type' => $blockIdentifier, 'fields' => $blockFields]],
        ], static fn (array $d): ?string => count($d['item_ids'] ?? []) === 2 ? null : 'expected 2 blocks', 'blocks');
        $context->step('the page lists its two blocks', 'relations.list', ['content_id' => $pageId], static fn (array $d): ?string => ($d['outgoing_total'] ?? 0) >= 2 ? null : 'the blocks are not linked');
        $context->step('add one more: what was there stays', 'site.add_blocks', [
            'content_id' => $pageId,
            'items' => [['type' => $blockIdentifier, 'fields' => $blockFields]],
        ]);
        $context->step('the page lists three', 'relations.list', ['content_id' => $pageId], static fn (array $d): ?string => ($d['outgoing_total'] ?? 0) >= 3 ? null : 'a block was lost');

        $context->step('the page is described block by block', 'page.describe', ['content_id' => $pageId], static fn (array $d): ?string => count($d['blocks'] ?? []) === 3 ? null : 'expected 3 blocks', 'described');
        $last = (int) (($context->answer('described')['blocks'][2]['content_id'] ?? 0));
        $first = (int) (($context->answer('described')['blocks'][0]['content_id'] ?? 0));
        $context->step('the last block goes first', 'page.block.move', ['content_id' => $pageId, 'block_content_id' => $last, 'position' => 0]);
        $context->step('the page shows it first', 'page.describe', ['content_id' => $pageId], static fn (array $d): ?string => ($d['blocks'][0]['content_id'] ?? 0) === $last ? null : 'the order did not change');
        $context->step('a block that is not on the page is refused', 'page.block.move', ['content_id' => $pageId, 'block_content_id' => 1, 'position' => 0], null, null, true);
        $context->step('a block is taken off the page', 'page.block.remove', ['content_id' => $pageId, 'block_content_id' => $first]);
        $context->step('the page keeps the two others', 'page.describe', ['content_id' => $pageId], static fn (array $d): ?string => count($d['blocks'] ?? []) === 2 ? null : 'expected 2 blocks');
        $context->step('the themes are listed', 'theme.list', [], static fn (array $d): ?string => ($d['designs'] ?? []) !== [] ? null : 'no design');
        $context->step('a design that does not exist is refused', 'theme.check', ['design' => 'no_such_design'], null, null, true);
        $context->step('the cards of a site are listed', 'item.types', ['siteaccess' => 'site'], static fn (array $d): ?string => is_array($d['items'] ?? null) ? null : 'no list');

        $context->step('the conventions of the pages are mined', 'site.conventions', ['location_id' => $context->parentLocationId], static fn (array $d): ?string => ($d['pages'] ?? 0) >= 1 && ($d['block_types'] ?? []) !== [] ? null : 'the page was not read', 'conventions');

        // A whole site, under the throwaway location (its media folder goes under the media root: it is tracked too). No image
        // is made: the selftest does not call the image model.
        $site = ['site_name' => 'Selftest ' . $context->stamp, 'domain' => 'selftest-' . $context->stamp . '.test', 'parent_location_id' => $context->parentLocationId, 'generate_images' => false, 'pages' => [['title' => 'About'], ['title' => 'Projects', 'children' => [['title' => 'One']]]]];
        $context->step('create a site with its pages', 'site.create_structure', $site, static fn (array $d): ?string => isset($d['site_id'], $d['media_folder_id']) && count($d['pages'] ?? []) === 2 && count($d['pages'][1]['children'] ?? []) === 1 ? null : 'the site or its pages are missing', 'site');
        foreach (['site_id', 'media_folder_id'] as $key) {
            if (($id = (int) ($context->answer('site')[$key] ?? 0)) > 0) {
                $context->track($id);
            }
        }
        // Read from the tree itself, not from the search index (which can lag behind what was just published).
        $built = $context->answer('site');
        $home = (int) ($built['home_page_location_id'] ?? 0);
        $projects = (int) ($built['pages'][1]['location_id'] ?? 0);
        $context->step('a page of the second level is under its parent, under the home page', 'location.list', ['content_id' => (int) ($built['pages'][1]['children'][0]['content_id'] ?? 0)], static function (array $d) use ($home, $projects): ?string {
            $location = $d['locations'][0] ?? [];

            return ($location['parent_location_id'] ?? 0) === $projects && str_contains((string) ($location['path'] ?? ''), '/' . $home . '/') ? null : 'the page "One" is not under "Projects" under the home page';
        });
        $this->configure($context, $built);
        $context->step('a site in an unknown language is refused', 'site.create_structure', ['language' => 'xx'] + $site, null, null, true);
        $context->step('a page with no title is refused', 'site.create_structure', ['pages' => [['title' => '']]] + $site, null, null, true);

        if ($allowed !== []) {
            $refused = $this->typeNotIn($allowed);
            $context->step('a type the page does not take is refused', 'site.add_blocks', ['content_id' => $pageId, 'items' => [['type' => $refused]]], null, null, true);
        }
        $context->step('an unknown language is refused', 'site.add_blocks', ['content_id' => $pageId, 'items' => [['type' => $blockIdentifier, 'fields' => $blockFields]], 'language' => 'xx'], null, null, true);
        $context->step('a field that is not a list is refused', 'site.add_blocks', ['content_id' => $pageId, 'link_field' => 'nope_field', 'items' => [['type' => $blockIdentifier]]], null, null, true);
        $context->step('an empty list of blocks is refused', 'site.add_blocks', ['content_id' => $pageId, 'items' => []], null, null, true);
    }

    /**
     * The configuration of the site just built: described, made as a draft, listed, changed, refused when it clashes, deleted.
     *
     * @param array<string, mixed> $built The answer of site.create_structure
     */
    private function configure(SelftestContext $context, array $built): void
    {
        $identifier = 'st_' . substr((string) preg_replace('/[^a-z0-9]/', '', strtolower($context->stamp)), 0, 20);
        $host = 'selftest-' . strtolower((string) preg_replace('/[^a-z0-9]/i', '', $context->stamp)) . '.test';
        $context->step('the options of a site are given', 'site.config.describe', [], static fn (array $d): ?string => ($d['options']['languages'] ?? []) !== [] && ($d['options']['designs'] ?? []) !== [] ? null : 'no language or no design', 'options');
        $options = $context->answer('options')['options'] ?? [];
        $language = (string) ($options['languages'][0] ?? '');
        $design = (string) ($options['designs'][0] ?? '');
        if ($language === '' || $design === '') {
            return;
        }
        $site = [
            'identifier' => $identifier,
            'name' => 'Selftest ' . $context->stamp,
            'root_location_id' => (int) ($built['home_page_location_id'] ?? 0),
            'layout_id' => (int) ($built['layout_id'] ?? 0),
            'design' => $design,
            'siteaccesses' => [['name' => $identifier . '_a', 'languages' => [$language], 'host' => $host]],
        ];
        $context->step('a design that does not exist is refused for a site', 'site.config.create', ['design' => 'no_such_design'] + $site, null, null, true);
        $context->step('a host that is not a host is refused', 'site.config.create', ['siteaccesses' => [['name' => $identifier . '_a', 'languages' => [$language], 'host' => 'Not A Host']]] + $site, null, null, true);
        $context->step('a layout that is not a content is refused', 'site.config.create', ['layout_id' => 999999999] + $site, null, null, true);
        $this->configured = $identifier;
        $context->step('configure the site as a draft', 'site.config.create', $site, static fn (array $d): ?string => ($d['site']['status'] ?? '') === 'draft' && ($d['site']['layout_remote_id'] ?? '') !== '' ? null : 'not a draft, or no layout', 'configured');
        $context->step('the registry lists it', 'site.config.list', [], static fn (array $d): ?string => in_array($identifier, array_column($d['sites'] ?? [], 'identifier'), true) ? null : 'not listed');
        $context->step('it is described as it was made', 'site.config.describe', ['identifier' => $identifier], static fn (array $d): ?string => ($d['site']['siteaccesses'][0]['host'] ?? '') === $host ? null : 'the host is not the one given');
        $context->step('a second site on the same address is refused', 'site.config.create', ['identifier' => $identifier . 'x', 'root_location_id' => (int) ($built['site_location_id'] ?? 0)] + $site, null, null, true);
        $context->step('a change keeps what is not given', 'site.config.update', ['identifier' => $identifier, 'name' => 'Renamed ' . $context->stamp], static fn (array $d): ?string => ($d['site']['name'] ?? '') === 'Renamed ' . $context->stamp && ($d['site']['design'] ?? '') === $design ? null : 'the name did not change, or the design was lost');
        $context->step('a draft cannot be the default siteaccess', 'site.config.default_siteaccess', ['siteaccess' => $identifier . '_a'], null, null, true);
        $context->step('its configuration is deleted', 'site.config.delete', ['identifier' => $identifier], static fn (array $d): ?string => ($d['identifier'] ?? '') === $identifier ? null : 'not deleted');
        $this->configured = null;
    }

    public function cleanup(): void
    {
        // The page is a tracked content: the selftest deletes it, and with it its blocks. A site configuration left by a failed run goes too.
        if ($this->configured !== null && $this->registry->find($this->configured) !== null) {
            $this->registry->delete($this->configured);
        }
        $this->configured = null;
    }

    /**
     * The first block type the page takes whose required fields can be filled with plain values.
     *
     * @param list<string> $allowed The types the blocks field accepts (empty: any type)
     *
     * @return array{0: string, 1: array<string, mixed>}|null
     */
    private function fillableBlock(array $allowed, string $stamp): ?array
    {
        $types = $this->repository->getContentTypeService();
        $candidates = $allowed;
        if ($candidates === []) {
            try {
                $group = $types->loadContentTypeGroupByIdentifier($this->conventionsSource->conventions()->blockGroup);
                foreach ($types->loadContentTypes($group) as $type) {
                    $candidates[] = $type->identifier;
                }
            } catch (Throwable) {
                return null;
            }
        }

        foreach ($candidates as $identifier) {
            try {
                $type = $types->loadContentTypeByIdentifier($identifier);
            } catch (Throwable) {
                continue;
            }
            $fields = RequiredFields::for($type, 'Selftest ' . $stamp);
            if ($fields !== null) {
                return [$identifier, $fields];
            }
        }

        return null;
    }

    /**
     * A content type that exists and is not in the list.
     *
     * @param list<string> $allowed
     */
    private function typeNotIn(array $allowed): string
    {
        foreach (['folder', 'user', 'image', 'article'] as $identifier) {
            if (!in_array($identifier, $allowed, true)) {
                return $identifier;
            }
        }

        return 'not_a_type';
    }
}
