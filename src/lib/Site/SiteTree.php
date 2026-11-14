<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Site;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;

/**
 * The tree of a site as the assistant reads it: the contents under a location, nested by their main location, a few
 * levels deep. It reads with the rights of the current user.
 */
readonly class SiteTree
{
    public function __construct(private Repository $repository)
    {
    }

    /**
     * @return array{root_location_id: int, total_items: int, children: list<array<string, mixed>>}
     */
    public function under(int $rootLocationId, int $depth = 2, ?string $contentType = null, int $limit = 500): array
    {
        $root = $this->repository->getLocationService()->loadLocation($rootLocationId);
        $criteria = [new Criterion\Subtree($root->getPathString())];
        if ($contentType !== null && $contentType !== '') {
            $criteria[] = new Criterion\ContentTypeIdentifier([$contentType]);
        }

        $items = [];
        foreach ($this->repository->getSearchService()->findContent(new Query(['filter' => new Criterion\LogicalAnd($criteria), 'limit' => $limit]))->searchHits as $hit) {
            /** @var Content $content */
            $content = $hit->valueObject;
            $location = $content->getContentInfo()->getMainLocation();
            if ($location === null) {
                continue;
            }
            $items[] = [
                'location_id' => $location->getId(),
                'parent_location_id' => $location->parentLocationId,
                'content_id' => $content->getId(),
                'content_type' => $content->getContentType()->getIdentifier(),
                'name' => $content->getName(),
            ];
        }

        return ['root_location_id' => $rootLocationId, 'total_items' => count($items), 'children' => self::nest($items, $rootLocationId, $depth)];
    }

    /**
     * @param list<array{location_id: int, parent_location_id: int, content_id: int, content_type: string, name: string|null}> $items
     *
     * @return list<array<string, mixed>>
     */
    private static function nest(array $items, int $parentId, int $depth): array
    {
        if ($depth <= 0) {
            return [];
        }

        $children = [];
        foreach ($items as $item) {
            if ($item['parent_location_id'] !== $parentId) {
                continue;
            }
            $node = ['location_id' => $item['location_id'], 'content_id' => $item['content_id'], 'content_type' => $item['content_type'], 'name' => $item['name']];
            $below = self::nest($items, $item['location_id'], $depth - 1);
            if ($below !== []) {
                $node['children'] = $below;
            }
            $children[] = $node;
        }

        return $children;
    }
}
