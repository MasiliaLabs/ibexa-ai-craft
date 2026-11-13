<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiCraft\Site;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Masilia\Bundle\IbexaAiCraft\Catalog\ContentCatalog;
use Masilia\Bundle\IbexaAiCraft\Support\Conventions;
use Masilia\Contracts\IbexaBlocks\ShownBlocks;
use Masilia\Bundle\IbexaAiCraft\Tool\CraftToolName;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolRegistry;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Composite exploration worker.
 *
 * Solves the admin-vs-front-office problem:
 * - The user is in admin context (which is its own siteaccess)
 * - They want to manage front-office siteaccesses (e.g. "fossilexit")
 * - We must discover front-office siteaccesses, match user input to one,
 *   resolve its root location, then browse the subtree under that root.
 *
 * All four exploration steps are executed in a single worker call so the
 * orchestrator LLM only sees ONE tool call, not four separate calls.
 */
final readonly class SiteExplorer
{
    public function __construct(
        private SiteAccessServiceInterface $siteAccessService,
        private ConfigResolverInterface    $configResolver,
        private ToolRegistry               $toolRegistry,
        private LoggerInterface            $aiLogger,
        private ?ContentCatalog            $contentCatalog = null,
        private Conventions                $conventions = new Conventions(),
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
                $rootLocationId = $this->resolveRoot($matched);
                $siteStructure = $this->browse($matched);
                $parentCandidates = $this->findParents($matched);
                $blockTypes = $this->listBlocks();
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

        // Substring containment — handles "fossil exit" → "fossilexit"
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

    private function resolveRoot(string $siteaccess): ?int
    {
        try {
            return (int) $this->configResolver->getParameter(
                'content.tree_root.location_id',
                scope: $siteaccess,
            );
        } catch (Throwable $e) {
            $this->aiLogger->warning('[SiteExplorer] Could not resolve root for {siteaccess}: {message}', [
                'siteaccess' => $siteaccess,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function browse(string $siteaccess): array
    {
        $tool = $this->toolRegistry->get(CraftToolName::BROWSE_SITE_STRUCTURE);
        if ($tool === null) {
            return [];
        }
        $result = $tool->execute([
            'siteaccess' => $siteaccess,
            'depth' => 2,
        ]);

        return $result->success ? $result->data : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findParents(string $siteaccess): array
    {
        $tool = $this->toolRegistry->get(CraftToolName::FIND_PARENT_CANDIDATES);
        if ($tool === null) {
            return [];
        }
        $result = $tool->execute([
            'content_type' => $this->conventions->pageType,
            'siteaccess' => $siteaccess,
        ]);

        return $result->success ? ($result->data['candidates'] ?? []) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listBlocks(): array
    {
        $tool = $this->toolRegistry->get(CraftToolName::LIST_BLOCKS);
        if ($tool === null) {
            return [];
        }
        $result = $tool->execute([]);

        return $result->success ? ($result->data['blocks'] ?? []) : [];
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

        $schema = $this->contentCatalog->getContentTypeSchema($this->conventions->pageType);
        if ($schema === null) {
            return [];
        }

        $blocksField = $schema['fields'][$this->conventions->blocksField] ?? null;
        if (!is_array($blocksField)) {
            return [];
        }

        return array_values($blocksField['allowedTypes'] ?? []);
    }
}
