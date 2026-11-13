<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiCraft\Capability;

use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Os\Capability\BaseCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class CreateStructureCapability extends BaseCapability
{
    public function __construct(private readonly ToolInterface $structureTool)
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
        return 'Creates the site container, the layout, the home page, the media folders and the pages (a tree of {title, description, children}) below the home page, '
            . 'and generates the images of the site. Use it for a new site only; to add to an existing site use content.create and site.add_blocks. '
            . 'The answer has site_id, home_page_id, home_page_location_id and pages.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['site_name', 'domain', 'pages'],
            'properties' => [
                'site_name' => ['type' => 'string', 'minLength' => 1, 'description' => 'Site name, e.g. "Fossil Exit"'],
                'domain' => ['type' => 'string', 'minLength' => 1, 'description' => 'Domain, e.g. "fossilexit.org"'],
                'description' => ['type' => 'string'],
                'siteaccess' => ['type' => 'string', 'description' => 'Siteaccess used to resolve the configuration'],
                'pages' => [
                    'type' => 'array',
                    'description' => 'The pages below the home page',
                    'items' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'description' => ['type' => 'string'], 'children' => ['type' => 'array']]],
                ],
                'language' => ['type' => 'string', 'description' => 'Language code the content is written in, e.g. "fre-FR" (default: the repository default language)'],
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
            ['Creates the site container, layout, home page, media folders and the pages, and generates images.'],
        );
    }

    public function execute(array $arguments): CapabilityResult
    {
        return self::fromTool($this->structureTool->execute($arguments));
    }
}
