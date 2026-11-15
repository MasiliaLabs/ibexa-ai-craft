<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Site;

use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Contracts\IbexaSites\Site;
use Masilia\Contracts\IbexaSites\SiteAccessDefinition;
use Masilia\Contracts\IbexaSites\SiteRegistry;
use Masilia\Contracts\IbexaSites\SiteStatus;

/**
 * What the capabilities of site configuration share: the arguments they take, the site they make of them, and how a site is told
 * (in an answer and on an approval card). The rules of what a site may be are the registry's (masilia/ibexa-sites): this only
 * reads and writes the shapes.
 */
final readonly class SiteConfigs
{
    public function __construct(private SiteRegistry $registry, private Repository $repository)
    {
    }

    /**
     * The properties of a site in the schema of a capability.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function properties(): array
    {
        $siteaccess = [
            'type' => 'object',
            'required' => ['name', 'languages', 'host'],
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Siteaccess name: lower case letters, digits and "_", unique in the project ("demo_fr")'],
                'languages' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1, 'description' => 'Ibexa language codes ("fre-FR"): the first is the language of the siteaccess, the others the ones content falls back to'],
                'host' => ['type' => 'string', 'description' => 'Host name, lower case ("demo.example.org")'],
                'prefix' => ['type' => 'string', 'description' => 'Path prefix ("fr" for /fr) when the host holds several siteaccesses; empty: the one at the root of the host'],
            ],
        ];

        return [
            'name' => ['type' => 'string', 'minLength' => 1, 'description' => 'The name shown in the admin'],
            'root_location_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'The location where the site starts (its home page). With site.create_structure write "$site.home_page_location_id"'],
            'design' => ['type' => 'string', 'description' => 'A design the project declares (site.config.describe lists them)'],
            'siteaccesses' => ['type' => 'array', 'minItems' => 1, 'items' => $siteaccess, 'description' => 'The siteaccesses visitors use: one per language, each with its host and, if the host holds several, a prefix'],
            'admin_siteaccesses' => ['type' => 'array', 'items' => $siteaccess, 'description' => 'The siteaccesses editors work in, on hosts of their own (same shape)'],
            'base' => ['type' => 'string', 'description' => 'A siteaccess group the site takes the settings it does not set from (images, rich text, menus)'],
            'var_dir' => ['type' => 'string', 'description' => 'Directory of the files of the site, relative to the project ("var/demo"): choose it before any image is added'],
            'layout_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'The content that holds the layout of the site (logo, footer). With site.create_structure write "$site.layout_id"'],
            'layout_remote_id' => ['type' => 'string', 'description' => 'Same, by remote id'],
            'session_cookie_domain' => ['type' => 'string', 'description' => 'Domain of the session cookie of the site and its admins (".demo.example.org"): it must cover all their hosts and no host of another site'],
            'admin_siteaccess' => ['type' => 'string', 'description' => 'An admin siteaccess of the project\'s YAML that edits the site'],
        ];
    }

    /** Whether an argument is still a reference to what an earlier step of a batch will answer ("$site.layout_id"). */
    public static function hasReference(mixed $value): bool
    {
        if (is_string($value)) {
            return str_starts_with($value, '$');
        }

        return is_array($value) && array_filter($value, static fn (mixed $v): bool => self::hasReference($v)) !== [];
    }

    /**
     * The site the arguments describe, on top of the one given (a change) or from nothing (a creation).
     *
     * @param array<string, mixed> $arguments
     */
    public function site(array $arguments, ?Site $current): Site
    {
        $has = static fn (string $key): bool => array_key_exists($key, $arguments);
        $text = static fn (string $key, ?string $keep): ?string => array_key_exists($key, $arguments) ? (trim((string) $arguments[$key]) === '' ? null : trim((string) $arguments[$key])) : $keep;
        $list = fn (string $key, array $keep): array => array_key_exists($key, $arguments)
            ? array_values(array_map(fn (mixed $row): SiteAccessDefinition => $this->definition((array) $row), (array) $arguments[$key]))
            : $keep;

        $layout = $current?->layoutRemoteId;
        if ($has('layout_id')) {
            $layout = $this->remoteId((int) $arguments['layout_id']);
        } elseif ($has('layout_remote_id')) {
            $layout = $text('layout_remote_id', null);
        }

        return new Site(
            trim((string) ($arguments['identifier'] ?? $current?->identifier)),
            $has('name') ? trim((string) $arguments['name']) : (string) $current?->name,
            $current !== null ? $current->status : SiteStatus::Draft,
            $has('root_location_id') ? (int) $arguments['root_location_id'] : (int) $current?->rootLocationId,
            $has('design') ? trim((string) $arguments['design']) : (string) $current?->design,
            $list('siteaccesses', $current !== null ? $current->siteaccesses : []),
            $text('base', $current?->base),
            $text('session_cookie_domain', $current?->sessionCookieDomain),
            $text('admin_siteaccess', $current?->adminSiteaccess),
            $text('var_dir', $current?->varDir),
            $layout,
            $list('admin_siteaccesses', $current !== null ? $current->adminSiteaccesses : []),
        );
    }

    /** @param array<string, mixed> $row */
    private function definition(array $row): SiteAccessDefinition
    {
        $prefix = trim((string) ($row['prefix'] ?? ''), " /\t");

        return new SiteAccessDefinition(
            trim((string) ($row['name'] ?? '')),
            array_values(array_map(static fn (mixed $l): string => trim((string) $l), (array) ($row['languages'] ?? []))),
            strtolower(trim((string) ($row['host'] ?? ''))),
            $prefix === '' ? null : strtolower($prefix),
        );
    }

    private function remoteId(int $contentId): ?string
    {
        try {
            return $this->repository->getContentService()->loadContentInfo($contentId)->remoteId;
        } catch (NotFoundException) {
            return null;
        }
    }

    /**
     * What is wrong with the arguments before the person is asked: the shape, then (when no value is a reference to an earlier
     * step) what the registry would refuse.
     *
     * @param array<string, mixed> $arguments
     */
    public function problem(array $arguments, bool $isNew): ?string
    {
        if (isset($arguments['layout_id'], $arguments['layout_remote_id'])) {
            return 'Give the layout either as layout_id or as layout_remote_id, not both.';
        }
        if (self::hasReference($arguments)) {
            return null;
        }
        if (isset($arguments['layout_id']) && $this->remoteId((int) $arguments['layout_id']) === null) {
            return sprintf('The content %d does not exist: it cannot be the layout.', (int) $arguments['layout_id']);
        }
        $identifier = trim((string) ($arguments['identifier'] ?? ''));
        $current = $this->registry->find($identifier);
        if ($isNew && $current !== null) {
            return sprintf('A site "%s" already exists: use site.config.update.', $identifier);
        }
        if (!$isNew && $current === null) {
            return sprintf('No site "%s" in the registry (site.config.list lists them).', $identifier);
        }
        $problems = $this->registry->problems($this->site($arguments, $isNew ? null : $current), $isNew);

        return $problems === [] ? null : implode(' ', $problems);
    }

    /** @return array<string, mixed> */
    public function describe(Site $site): array
    {
        $line = static fn (SiteAccessDefinition $s): array => ['name' => $s->name, 'languages' => $s->languages, 'host' => $s->host, 'prefix' => $s->uriPrefix];

        return [
            'identifier' => $site->identifier,
            'name' => $site->name,
            'status' => $site->status->value,
            'root_location_id' => $site->rootLocationId,
            'design' => $site->design,
            'base' => $site->base,
            'var_dir' => $site->varDir,
            'layout_remote_id' => $site->layoutRemoteId,
            'session_cookie_domain' => $site->sessionCookieDomain,
            'admin_siteaccess' => $site->adminSiteaccess,
            'siteaccesses' => array_map($line, $site->siteaccesses),
            'admin_siteaccesses' => array_map($line, $site->adminSiteaccesses),
        ];
    }

    /** @return list<array{0: string, 1: string}> The rows of an approval card for a site */
    public function rows(Site $site): array
    {
        $shown = static fn (array $list): string => implode('; ', array_map(
            static fn (SiteAccessDefinition $s): string => sprintf('%s (%s) on %s%s', $s->name, implode(' → ', $s->languages), $s->host, $s->uriPrefix === null ? '' : '/' . $s->uriPrefix),
            $list,
        ));
        $rows = [
            ['Site', sprintf('%s (%s)', $site->name, $site->identifier)],
            ['Status', $site->status->value],
            ['Root location', (string) $site->rootLocationId],
            ['Design', $site->design],
            ['Siteaccesses', $shown($site->siteaccesses) ?: 'none'],
        ];
        foreach ([['Admin siteaccesses', $shown($site->adminSiteaccesses)], ['Base group', (string) $site->base], ['Files directory', (string) $site->varDir], ['Layout', (string) $site->layoutRemoteId], ['Session cookie domain', (string) $site->sessionCookieDomain]] as [$label, $value]) {
            if ($value !== '') {
                $rows[] = [$label, $value];
            }
        }

        return $rows;
    }
}
