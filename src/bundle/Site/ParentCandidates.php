<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiCraft\Site;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;

/** Where a new page can go in a site: the contents of a type (the pages) under its root, by name. */
readonly class ParentCandidates
{
    public function __construct(private Repository $repository)
    {
    }

    /**
     * @return list<array{location_id: int, name: string|null, content_type: string}>
     */
    public function under(int $rootLocationId, string $contentType, int $limit = 20): array
    {
        $root = $this->repository->getLocationService()->loadLocation($rootLocationId);
        $result = $this->repository->getSearchService()->findContent(new Query([
            'filter' => new Criterion\LogicalAnd([new Criterion\Subtree($root->getPathString()), new Criterion\ContentTypeIdentifier([$contentType])]),
            'limit' => $limit,
            'sortClauses' => [new Query\SortClause\ContentName(Query::SORT_ASC)],
        ]));

        $candidates = [];
        foreach ($result->searchHits as $hit) {
            $content = $hit->valueObject;
            $location = $content->getContentInfo()->getMainLocation();
            if ($location !== null) {
                $candidates[] = ['location_id' => $location->getId(), 'name' => $content->getName(), 'content_type' => $content->getContentType()->getIdentifier()];
            }
        }

        return $candidates;
    }
}
