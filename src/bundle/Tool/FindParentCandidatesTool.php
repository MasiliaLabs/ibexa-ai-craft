<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiCraft\Tool;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\ContentTypeIdentifier;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\LogicalAnd;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\Subtree;
use Masilia\Bundle\IbexaAi\Agent\Tool\AgentErrorHelper;
use Masilia\Bundle\IbexaAi\Agent\Tool\SiteaccessLocationResolver;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;
use Masilia\Bundle\IbexaAiCraft\Support\Conventions;
use Psr\Log\LoggerInterface;

/**
 * Finds candidate parent locations for content creation.
 *
 * Searches for existing content of the requested type under the
 * current (or specified) siteaccess root.
 */
final readonly class FindParentCandidatesTool implements ToolInterface
{
    public function __construct(
        private Repository $repository,
        private SiteaccessLocationResolver $locationResolver,
        private LoggerInterface $aiLogger,
        private Conventions $conventions = new Conventions(),
    ) {
    }

    public function getName(): string
    {
        return CraftToolName::FIND_PARENT_CANDIDATES;
    }

    public function getDescription(): string
    {
        return 'Find candidate parent locations for creating content. Returns existing pages and siteaccess roots where new content can be placed.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content_type' => [
                    'type' => 'string',
                    'description' => 'Content type identifier to search for (e.g. "page", "article")',
                ],
                'siteaccess' => [
                    'type' => 'string',
                    'description' => 'Limit search to this siteaccess (optional)',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max candidates to return (default: 20)',
                    'default' => 20,
                ],
            ],
            'required' => ['content_type'],
        ];
    }

    public function execute(array $params): ToolResult
    {
        try {
            $contentTypeIdentifier = $params['content_type'] ?? $this->conventions->pageType;
            $siteaccess = $params['siteaccess'] ?? '';
            $limit = $params['limit'] ?? 20;

            $candidates = $this->searchSiteaccess($siteaccess, $contentTypeIdentifier, $limit);

            return ToolResult::ok(
                sprintf('Found %d candidate locations', count($candidates)),
                ['candidates' => $candidates, 'total' => count($candidates)],
            );
        } catch (\Throwable $e) {
            return AgentErrorHelper::handle($this->aiLogger, $e, 'find parent candidates');
        }
    }

    /**
     * @return array<int, array{location_id: int, name: string, content_type: string, siteaccess: string}>
     */
    private function searchSiteaccess(string $siteaccess, string $contentTypeIdentifier, int $limit): array
    {
        $rootLocationId = $this->locationResolver->resolve($siteaccess);
        if ($rootLocationId === null) {
            return [];
        }

        $searchService = $this->repository->getSearchService();
        $locationService = $this->repository->getLocationService();

        try {
            $rootLocation = $locationService->loadLocation($rootLocationId);
        } catch (\Throwable) {
            return [];
        }

        $query = new Query([
            'filter' => new LogicalAnd([
                new Subtree($rootLocation->getPathString()),
                new ContentTypeIdentifier([$contentTypeIdentifier]),
            ]),
            'limit' => $limit,
            'sortClauses' => [new Query\SortClause\ContentName(Query::SORT_ASC)],
        ]);

        $result = $searchService->findContent($query);
        $candidates = [];

        foreach ($result->searchHits as $hit) {
            $content = $hit->valueObject;
            $mainLocation = $content->getContentInfo()->getMainLocation();

            $candidates[] = [
                'location_id' => $mainLocation->getId(),
                'name' => $content->getName(),
                'content_type' => $content->getContentType()->getIdentifier(),
                'siteaccess' => $siteaccess ?: 'current',
            ];
        }

        return $candidates;
    }
}
