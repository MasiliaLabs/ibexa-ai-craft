<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Site;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\IbexaAiCraft\Support\Conventions;
use Masilia\IbexaAiCraft\Support\ConventionsSource;
use Masilia\Contracts\IbexaAi\Content\ContentWriter;
use Masilia\Contracts\IbexaAi\Content\Languages;
use Masilia\Contracts\IbexaAi\Image\ImageGenerator;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Builds the skeleton of a new site, the Masilia way: the site container (under the root of the content structure, or a
 * location given), its layout configuration and its home page, a media folder with Images, Files and Multimedia (under the
 * root of the media), and the pages below the home page. The content types are the conventions'; the roots are the
 * siteaccess's (location_ids.content_structure, location_ids.media).
 *
 * {@see problem()} says, without writing anything, what would stop the build; {@see build()} writes everything in one
 * transaction (a failure leaves nothing behind), then asks for the favicon, which can fail on its own.
 */
final readonly class SiteBuilder
{
    public const MAX_PAGES = 50;
    public const MAX_DEPTH = 3;

    /** What each content of the skeleton is given, besides the blocks field of the pages. */
    private const SITE_FIELDS = ['title', 'domain', 'description'];
    private const LAYOUT_FIELDS = ['bo_title'];
    /** A layout of the Masilia socle asks for a logo text: it is given the name of the site (HTML, as a rich text field takes it). */
    private const LAYOUT_LOGO_FIELD = 'logo_text';
    private const PAGE_FIELDS = ['title', 'description'];
    private const HOME_FIELDS = ['title'];
    private const FOLDER_FIELDS = ['name'];
    private const FAVICON_FIELD = 'favicon';
    private const MEDIA_FOLDERS = ['Images', 'Files', 'Multimedia'];

    public function __construct(
        private Repository $repository,
        private ContentWriter $writer,
        private Languages $languages,
        private ConfigResolverInterface $config,
        private ImageGenerator $images,
        private LoggerInterface $aiLogger,
        private ConventionsSource $conventionsSource = new Conventions(),
        private ?BlocksFolders $blocksFolders = null,
    ) {
    }

    /**
     * What would stop the build, or null.
     *
     * @param array<string, mixed> $arguments The arguments of site.create_structure, already checked against its schema
     */
    public function problem(array $arguments): ?string
    {
        if (isset($arguments['language']) && $this->languages->resolve((string) $arguments['language']) === null) {
            return sprintf('The language "%s" does not exist or is disabled (use a code like "fre-FR", or "fr"): see localization.language.list.', $arguments['language']);
        }

        $pages = $this->pagesProblem((array) ($arguments['pages'] ?? []), 1);
        if ($pages !== null) {
            return $pages;
        }
        if (self::pageCount((array) ($arguments['pages'] ?? [])) > self::MAX_PAGES) {
            return sprintf('A site is created with %d pages at most; add the others with content.create afterwards.', self::MAX_PAGES);
        }

        $blocks = $this->conventionsSource->conventions()->blocksField;
        foreach ([
            [$this->conventionsSource->conventions()->siteType, ($arguments['generate_images'] ?? true) ? [...self::SITE_FIELDS, self::FAVICON_FIELD] : self::SITE_FIELDS],
            [$this->conventionsSource->conventions()->layoutType, $this->layoutFields()],
            [$this->conventionsSource->conventions()->homePageType, [...self::HOME_FIELDS, $blocks]],
            [$this->conventionsSource->conventions()->pageType, [...self::PAGE_FIELDS, $blocks]],
            [$this->conventionsSource->conventions()->folderType, self::FOLDER_FIELDS],
        ] as [$type, $fields]) {
            $problem = $this->typeProblem($type, $fields);
            if ($problem !== null) {
                return $problem;
            }
        }

        foreach (['the site' => $this->siteParent($arguments), 'the media' => $this->mediaRoot($arguments)] as $what => $locationId) {
            if ($locationId === null || $this->location($locationId) === null) {
                return sprintf('There is no location to put %s under (%s).', $what, $locationId === null ? 'none is configured' : 'location ' . $locationId . ' does not exist');
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $arguments The arguments of site.create_structure, without a {@see problem()}
     *
     * @return array{site_id: int, site_location_id: int, home_page_id: int, home_page_location_id: int, layout_id: int, media_folder_id: int, pages: list<array<string, mixed>>, favicon: bool}
     */
    public function build(array $arguments): array
    {
        $language = isset($arguments['language']) ? ($this->languages->resolve((string) $arguments['language']) ?? $this->languages->default()) : $this->languages->default();
        $name = trim((string) $arguments['site_name']);
        $blocks = $this->conventionsSource->conventions()->blocksField;

        $this->repository->beginTransaction();
        try {
            [$site, $siteLocation] = $this->create($this->conventionsSource->conventions()->siteType, (int) $this->siteParent($arguments), [
                'title' => $name,
                'domain' => trim((string) $arguments['domain']),
                'description' => (string) ($arguments['description'] ?? ''),
            ], $language);
            $layoutFields = ['bo_title' => sprintf('%s Layout Configuration', $name)];
            if (in_array(self::LAYOUT_LOGO_FIELD, $this->layoutFields(), true)) {
                $layoutFields[self::LAYOUT_LOGO_FIELD] = '<p>' . htmlspecialchars($name, ENT_QUOTES | ENT_XML1) . '</p>';
            }
            [$layout] = $this->create($this->conventionsSource->conventions()->layoutType, $siteLocation, $layoutFields, $language);
            [$home, $homeLocation] = $this->create($this->conventionsSource->conventions()->homePageType, $siteLocation, ['title' => 'Home', $blocks => []], $language);
            $this->blocksFolders?->ensureIfPossible($home, $language);

            [$media, $mediaLocation] = $this->create($this->conventionsSource->conventions()->folderType, (int) $this->mediaRoot($arguments), ['name' => sprintf('%s Media', $name)], $language);
            foreach (self::MEDIA_FOLDERS as $folder) {
                $this->create($this->conventionsSource->conventions()->folderType, $mediaLocation, ['name' => $folder], $language);
            }

            $pages = $this->pages((array) ($arguments['pages'] ?? []), $homeLocation, $language);
            $this->repository->commit();
        } catch (Throwable $e) {
            $this->repository->rollback();
            throw $e;
        }

        return [
            'site_id' => $site,
            'site_location_id' => $siteLocation,
            'home_page_id' => $home,
            'home_page_location_id' => $homeLocation,
            'layout_id' => $layout,
            'media_folder_id' => $media,
            'pages' => $pages,
            // After the transaction: an image that cannot be made does not undo the site.
            'favicon' => ($arguments['generate_images'] ?? true) && $this->favicon($site, $name, $language),
        ];
    }

    /**
     * @param list<mixed> $pages
     *
     * @return list<array<string, mixed>>
     */
    private function pages(array $pages, int $parentLocationId, string $language): array
    {
        $created = [];
        foreach ($pages as $page) {
            $title = trim((string) $page['title']);
            [$id, $location] = $this->create($this->conventionsSource->conventions()->pageType, $parentLocationId, [
                'title' => $title,
                'description' => (string) ($page['description'] ?? ''),
                $this->conventionsSource->conventions()->blocksField => [],
            ], $language);
            $this->blocksFolders?->ensureIfPossible($id, $language);
            $entry = ['content_id' => $id, 'location_id' => $location, 'title' => $title];
            if (!empty($page['children'])) {
                $entry['children'] = $this->pages((array) $page['children'], $location, $language);
            }
            $created[] = $entry;
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array{0: int, 1: int} The content id and its main location id
     */
    private function create(string $type, int $parentLocationId, array $fields, string $language): array
    {
        $published = $this->writer->create($type, [$parentLocationId], $fields, $language);
        if ($published->location === null) {
            throw new RuntimeException(sprintf('The %s was created without a location.', $type));
        }

        return [(int) $published->content->getId(), (int) $published->location->getId()];
    }

    private function favicon(int $siteId, string $name, string $language): bool
    {
        if (!$this->images->isConfigured()) {
            $this->aiLogger->info('[craft] No image model for this siteaccess: the site gets no favicon.');

            return false;
        }

        try {
            $this->writer->update($siteId, [self::FAVICON_FIELD => ['description' => sprintf('Simple clean favicon icon for "%s" website, minimalist, professional', $name), 'size' => '1:1']], $language);

            return true;
        } catch (Throwable $e) {
            $this->aiLogger->warning('[craft] The favicon of "{site}" could not be made: {message}', ['site' => $name, 'message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * The fields the builder gives the layout: its title, and the logo text when the type has one (the socle of masilia/ibexa-blocks
     * makes it required, so a layout could not be made without it).
     *
     * @return list<string>
     */
    private function layoutFields(): array
    {
        try {
            $has = $this->repository->getContentTypeService()->loadContentTypeByIdentifier($this->conventionsSource->conventions()->layoutType)->getFieldDefinition(self::LAYOUT_LOGO_FIELD) !== null;
        } catch (Throwable) {
            $has = false;
        }

        return $has ? [...self::LAYOUT_FIELDS, self::LAYOUT_LOGO_FIELD] : self::LAYOUT_FIELDS;
    }

    /**
     * @param list<string> $filled The fields the builder gives it
     */
    private function typeProblem(string $identifier, array $filled): ?string
    {
        try {
            $type = $this->repository->getContentTypeService()->loadContentTypeByIdentifier($identifier);
        } catch (Throwable) {
            return sprintf('There is no content type "%s" to build a site with (see the conventions of masilia_ibexa_ai_craft).', $identifier);
        }

        $missing = [];
        $unfilled = [];
        foreach ($filled as $field) {
            if ($type->getFieldDefinition($field) === null) {
                $missing[] = $field;
            }
        }
        foreach ($type->getFieldDefinitions() as $definition) {
            if ($definition->isRequired && !in_array($definition->identifier, $filled, true)) {
                $unfilled[] = $definition->identifier;
            }
        }

        return match (true) {
            $missing !== [] => sprintf('The content type "%s" has no field %s, which a site needs.', $identifier, implode(', ', $missing)),
            $unfilled !== [] => sprintf('The content type "%s" has required fields a new site cannot fill: %s.', $identifier, implode(', ', $unfilled)),
            default => null,
        };
    }

    /**
     * @param array<mixed> $pages As the model gave them: not even sure to be a list
     */
    private function pagesProblem(array $pages, int $depth): ?string
    {
        if (!array_is_list($pages)) {
            return 'pages must be a list of {title, description?, children?}.';
        }
        foreach ($pages as $index => $page) {
            if (!is_array($page) || !is_string($page['title'] ?? null) || trim($page['title']) === '') {
                return sprintf('Page %d (level %d) has no title.', $index + 1, $depth);
            }
            if (!empty($page['children'])) {
                if ($depth >= self::MAX_DEPTH) {
                    return sprintf('Pages are nested %d levels deep at most.', self::MAX_DEPTH);
                }
                $problem = $this->pagesProblem((array) $page['children'], $depth + 1);
                if ($problem !== null) {
                    return sprintf('Page "%s": %s', $page['title'], $problem);
                }
            }
        }

        return null;
    }

    /** @param list<mixed> $pages */
    private static function pageCount(array $pages): int
    {
        $count = 0;
        foreach ($pages as $page) {
            $count += 1 + (is_array($page) && !empty($page['children']) ? self::pageCount((array) $page['children']) : 0);
        }

        return $count;
    }

    /** @param array<string, mixed> $arguments */
    private function siteParent(array $arguments): ?int
    {
        return isset($arguments['parent_location_id']) ? (int) $arguments['parent_location_id'] : $this->configured('location_ids.content_structure', $arguments);
    }

    /** @param array<string, mixed> $arguments */
    private function mediaRoot(array $arguments): ?int
    {
        return $this->configured('location_ids.media', $arguments);
    }

    /** @param array<string, mixed> $arguments */
    private function configured(string $parameter, array $arguments): ?int
    {
        $siteaccess = trim((string) ($arguments['siteaccess'] ?? ''));
        try {
            return (int) $this->config->getParameter($parameter, null, $siteaccess !== '' ? $siteaccess : null);
        } catch (Throwable) {
            return null;
        }
    }

    private function location(int $id): ?Location
    {
        try {
            return $this->repository->getLocationService()->loadLocation($id);
        } catch (Throwable) {
            return null;
        }
    }
}
