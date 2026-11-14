<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Capability;

use Masilia\IbexaAiCraft\Site\SiteBuilder;
use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class CreateStructureCapability extends AbstractCapability
{
    public function __construct(private readonly SiteBuilder $builder)
    {
    }

    public function name(): string
    {
        return 'site.create_structure';
    }

    public function summary(): string
    {
        return 'Create a whole site skeleton: the site, its layout configuration, home page, media folders and pages.';
    }

    public function description(): string
    {
        return 'Creates the site container, the layout, the home page, the media folders and the pages (a tree of {title, description, children}, '
            . sprintf('%d pages and %d levels at most) below the home page, all or nothing, then generates the favicon of the site. ', SiteBuilder::MAX_PAGES, SiteBuilder::MAX_DEPTH)
            . 'Use it for a new site only; to add to an existing site use content.create and site.add_blocks. '
            . 'The answer has site_id, home_page_id, home_page_location_id, media_folder_id and pages.';
    }

    public function inputSchema(): array
    {
        $page = ['type' => 'object', 'required' => ['title'], 'properties' => ['title' => ['type' => 'string', 'minLength' => 1], 'description' => ['type' => 'string'], 'children' => ['type' => 'array']]];

        return [
            'type' => 'object',
            'required' => ['site_name', 'domain', 'pages'],
            'properties' => [
                'site_name' => ['type' => 'string', 'minLength' => 1, 'description' => 'Site name, e.g. "Fossil Exit"'],
                'domain' => ['type' => 'string', 'minLength' => 1, 'description' => 'Domain, e.g. "fossilexit.org"'],
                'description' => ['type' => 'string'],
                'pages' => ['type' => 'array', 'description' => 'The pages below the home page', 'items' => $page],
                'language' => ['type' => 'string', 'description' => 'Language code the content is written in, e.g. "fre-FR" or "fr" (default: the default language of the repository)'],
                'parent_location_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Where the site container goes (default: the root of the content structure)'],
                'siteaccess' => ['type' => 'string', 'description' => 'The siteaccess whose roots are used (default: the current one)'],
                'generate_images' => ['type' => 'boolean', 'description' => 'Make the favicon with the image model (default: true)'],
            ],
        ];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['content', 'create'], ['content', 'publish']];
    }

    public function examples(): array
    {
        return [['description' => 'A small site', 'arguments' => ['site_name' => 'Solar Atlas', 'domain' => 'solaratlas.org', 'pages' => [['title' => 'About'], ['title' => 'Projects', 'children' => [['title' => 'Morocco']]]]]]];
    }

    public function validate(array $arguments): ?string
    {
        return $this->builder->problem($arguments);
    }

    public function preview(array $arguments): Preview
    {
        $titles = array_map(static fn ($page): string => is_array($page) ? (string) ($page['title'] ?? '') : '', array_values((array) $arguments['pages']));

        return new Preview(
            'Create a site',
            [
                ['Site', (string) $arguments['site_name']],
                ['Domain', (string) $arguments['domain']],
                ['Pages', implode(', ', array_filter($titles)) ?: 'none'],
                ['Language', (string) ($arguments['language'] ?? 'default')],
            ],
            [($arguments['generate_images'] ?? true)
                ? 'Creates the site container, layout, home page, media folders and the pages, then generates the favicon.'
                : 'Creates the site container, layout, home page, media folders and the pages.'],
        );
    }

    public function execute(array $arguments): CapabilityResult
    {
        $built = $this->builder->build($arguments);

        return CapabilityResult::ok(sprintf('Created the site "%s" with %d top-level pages.', trim((string) $arguments['site_name']), count($built['pages'])), $built);
    }
}
