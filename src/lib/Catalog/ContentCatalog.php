<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Catalog;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Masilia\IbexaAiCraft\Support\Conventions;
use Masilia\Contracts\IbexaAi\Content\ContentTypeSchemas;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Catalogs standard Ibexa content types (page, article, folder, etc.)
 * from the non-"Blocks" content type groups.
 *
 * Complements BlockCatalog which only covers block layout types.
 * The content model of the site: the schema of each content type, as the assistant reads it (ContentTypeSchemas).
 * the LLM might propose in a plan.
 */
final class ContentCatalog
{
    private const CACHE_KEY = 'masilia_ai_craft.content_catalog.schemas.v1';
    private const CACHE_TTL = 3600;

    /**
     * Request-scoped memoization.
     *
     * @var array<string, array{identifier: string, name: string, fields: array<string, array<string, mixed>>}>|null
     */
    private ?array $memo = null;

    public function __construct(
        private readonly ContentTypeService $contentTypeService,
        private readonly CacheItemPoolInterface $cache,
        private readonly ContentTypeSchemas $schemas,
        private readonly Conventions $conventions = new Conventions(),
    ) {
    }

    /**
     * Get the schema for a single content type by identifier.
     *
     * @return array{identifier: string, name: string, fields: array<string, array<string, mixed>>}|null
     */
    public function getContentTypeSchema(string $identifier): ?array
    {
        return $this->getAvailableContentTypes()[$identifier] ?? null;
    }

    /**
     * Get all standard content types with detailed per-field schemas.
     *
     * @return array<string, array{identifier: string, name: string, fields: array<string, array<string, mixed>>}>
     */
    public function getAvailableContentTypes(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $item = $this->cache->getItem(self::CACHE_KEY . '.' . md5(implode('|', $this->conventions->contentGroups)));
        if ($item->isHit()) {
            return $this->memo = $item->get();
        }

        $types = [];
        foreach ($this->loadContentTypes() as $type) {
            $types[$type->getIdentifier()] = [
                'identifier' => $type->getIdentifier(),
                'name' => $type->getName(),
                'fields' => $this->schemas->fields($type),
            ];
        }

        $item->set($types)->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        return $this->memo = $types;
    }

    /**
     * @return ContentType[]
     */
    private function loadContentTypes(): array
    {
        $allTypes = [];

        foreach ($this->conventions->contentGroups as $groupIdentifier) {
            try {
                $group = $this->contentTypeService->loadContentTypeGroupByIdentifier($groupIdentifier);
            } catch (NotFoundException) {
                continue;
            }

            $types = $this->contentTypeService->loadContentTypes($group);
            if (!is_iterable($types)) {
                continue;
            }

            foreach ($types as $type) {
                $allTypes[] = $type;
            }
        }

        return $allTypes;
    }
}
