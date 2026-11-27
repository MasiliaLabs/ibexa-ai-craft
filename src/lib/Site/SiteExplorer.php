<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Site;

use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Masilia\IbexaAiCraft\Catalog\BlockCatalog;
use Masilia\IbexaAiCraft\Catalog\ContentCatalog;
use Masilia\IbexaAiCraft\Support\Conventions;
use Masilia\IbexaAiCraft\Support\ConventionsSource;
use Masilia\Contracts\IbexaAi\Site\SiteLocations;
use Masilia\Contracts\IbexaBlocks\ShownBlocks;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * What the assistant needs to know of a front-office site before it builds pages there, in one answer: the siteaccesses
 * (the editor works in the admin, which is a siteaccess of its own), the one the user means (matched loosely: "acme
 * exit" is "acmeshop"), its root, its tree, where a page can go, and the blocks a page takes.
 */
final readonly class SiteExplorer
{
    public function __construct(
        private SiteAccessServiceInterface $siteAccessService,
        private SiteLocations              $locations,
        private SiteTree                   $tree,
        private ParentCandidates           $parents,
        private BlockCatalog               $blocks,
        private LoggerInterface            $aiLogger,
        private ?ContentCatalog            $contentCatalog = null,
        private ConventionsSource                $conventionsSource = new Conventions(),
        /** With masilia/ibexa-blocks: the block types the design of the site can show; the others are left out of the answer */
        private ?ShownBlocks               $shownBlocks = null,
    ) {
    }

    public function explore(string $siteaccess): ExplorationResult
    {
        try {
            $siteaccesses = $this->listSiteaccesses();
            $matched = $this->matchSiteaccess($siteaccess, $siteaccesses);
            $rootLocationId = null;
            $siteStructure = [];
            $parentCandidates = [];
            $blockTypes = [];
            $notShownBlockTypes = [];

            if ($matched !== null) {
                $rootLocationId = $this->locations->rootLocationId($matched);
                $siteStructure = $rootLocationId !== null ? $this->browse($matched, $rootLocationId) : [];
                $parentCandidates = $rootLocationId !== null ? $this->findParents($matched, $rootLocationId) : [];
                $blockTypes = $this->blocks->getAvailableBlocks();
                $parentBlocksAllowedTypes = $this->resolveParentBlocksAllowedTypes();

                // A block the theme of the site has no template for is not shown there (the page answers 404): do not offer it.
                $shown = $this->shownBlocks?->forSiteaccess($matched) ?? [];
                if ($shown !== []) {
                    $identifier = static fn (mixed $block): string => (string) (is_array($block) ? ($block['identifier'] ?? '') : $block);
                    $notShownBlockTypes = array_values(array_unique(array_filter(
                        array_map($identifier, [...$blockTypes, ...$parentBlocksAllowedTypes]),
                        static fn (string $type): bool => $type !== '' && !in_array($type, $shown, true),
                    )));
                    $blockTypes = array_values(array_filter($blockTypes, static fn (mixed $block): bool => in_array($identifier($block), $shown, true)));
                    $parentBlocksAllowedTypes = array_values(array_filter($parentBlocksAllowedTypes, static fn (string $type): bool => in_array($type, $shown, true)));
                }
            }

            return new ExplorationResult(
                siteaccesses: $siteaccesses,
                matchedSiteaccess: $matched,
                rootLocationId: $rootLocationId,
                siteStructure: $siteStructure,
                parentCandidates: $parentCandidates,
                blockTypes: $blockTypes,
                parentBlocksAllowedTypes: $parentBlocksAllowedTypes ?? [],
                notShownBlockTypes: $notShownBlockTypes,
            );
        } catch (Throwable $e) {
            $this->aiLogger->error('[SiteExplorer] Exploration failed: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new ExplorationResult(
                siteaccesses: $this->listSiteaccesses(),
                matchedSiteaccess: null,
                rootLocationId: null,
                siteStructure: [],
                parentCandidates: [],
                blockTypes: [],
                errorMessage: $e->getMessage(),
            );
        }
    }

    /**
     * @return list<string>
     */
    private function listSiteaccesses(): array
    {
        $names = [];
        foreach ($this->siteAccessService->getAll() as $sa) {
            $names[] = $sa->name;
        }
        sort($names);

        return $names;
    }

    /**
     * @param list<string> $siteaccesses
     */
    private function matchSiteaccess(string $input, array $siteaccesses): ?string
    {
        $needle = $this->normalize($input);

        // Exact match wins
        foreach ($siteaccesses as $name) {
            if ($this->normalize($name) === $needle) {
                return $name;
            }
        }

        // Substring containment — handles "acme shop" → "acmeshop"
        foreach ($siteaccesses as $name) {
            if ($needle !== '' && str_contains($this->normalize($name), $needle)) {
                return $name;
            }
        }

        return null;
    }

    private function normalize(string $s): string
    {
        return strtolower(str_replace(['_', '-', ' '], '', $s));
    }

    /**
     * The tree of the site, two levels deep; empty when it cannot be read (what else the answer has is still useful).
     *
     * @return array<string, mixed>
     */
    private function browse(string $siteaccess, int $rootLocationId): array
    {
        try {
            return ['siteaccess' => $siteaccess] + $this->tree->under($rootLocationId, 2);
        } catch (Throwable $e) {
            $this->aiLogger->warning('[SiteExplorer] The tree of {siteaccess} could not be read: {message}', ['siteaccess' => $siteaccess, 'message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * The pages of the site, where a new page can go.
     *
     * @return list<array<string, mixed>>
     */
    private function findParents(string $siteaccess, int $rootLocationId): array
    {
        try {
            return array_map(static fn (array $candidate): array => $candidate + ['siteaccess' => $siteaccess], $this->parents->under($rootLocationId, $this->conventionsSource->conventions()->pageType));
        } catch (Throwable $e) {
            $this->aiLogger->warning('[SiteExplorer] The pages of {siteaccess} could not be read: {message}', ['siteaccess' => $siteaccess, 'message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Extract the allowed block type identifiers from the page content type's
     * "blocks" field (ezobjectrelationlist selectionContentTypes).
     *
     * @return list<string>
     */
    private function resolveParentBlocksAllowedTypes(): array
    {
        if ($this->contentCatalog === null) {
            return [];
        }

        $schema = $this->contentCatalog->getContentTypeSchema($this->conventionsSource->conventions()->pageType);
        if ($schema === null) {
            return [];
        }

        $blocksField = $schema['fields'][$this->conventionsSource->conventions()->blocksField] ?? null;
        if (!is_array($blocksField)) {
            return [];
        }

        return array_values($blocksField['allowedTypes'] ?? []);
    }
}
