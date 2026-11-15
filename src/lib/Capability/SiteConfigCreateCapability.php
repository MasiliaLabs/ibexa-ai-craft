<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Capability;

use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Masilia\Contracts\IbexaSites\SiteInvalid;
use Masilia\Contracts\IbexaSites\SiteRegistry;
use Masilia\IbexaAiCraft\Site\SiteConfigs;

final class SiteConfigCreateCapability extends AbstractCapability
{
    public function __construct(private readonly SiteRegistry $registry, private readonly SiteConfigs $configs)
    {
    }

    public function name(): string
    {
        return 'site.config.create';
    }

    public function summary(): string
    {
        return 'Configure a site so that it is served: its siteaccesses (languages, hosts, prefixes), design, root, admin siteaccesses.';
    }

    public function description(): string
    {
        return 'Creates the configuration of a site in the registry, as a draft (nothing is served yet; activate it with site.config.set_status). '
            . 'The site structure (the contents) is made by site.create_structure: in one propose_changes write "$site.home_page_location_id" for root_location_id and "$site.layout_id" for layout_id. '
            . 'Choose the design, languages and base group among site.config.describe options. Each siteaccess has a host and, when a host holds several, a prefix ("fr" for /fr); the one without a prefix is at the root of the host. '
            . 'The hosts must also answer at the server (DNS, certificate): the registry does not set that.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['identifier', 'name', 'root_location_id', 'design', 'siteaccesses'],
            'properties' => ['identifier' => ['type' => 'string', 'description' => 'The name of the siteaccess group of the site: lower case letters, digits and "_", 2 to 30 characters ("demo_group")']] + SiteConfigs::properties(),
        ];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['masilia_site', 'manage']];
    }

    public function examples(): array
    {
        return [['description' => 'A site in two languages under one host', 'arguments' => [
            'identifier' => 'demo_group',
            'name' => 'Demo',
            'root_location_id' => 1044,
            'design' => 'standard',
            'siteaccesses' => [
                ['name' => 'demo', 'languages' => ['eng-GB'], 'host' => 'demo.example.org'],
                ['name' => 'demo_fr', 'languages' => ['fre-FR', 'eng-GB'], 'host' => 'demo.example.org', 'prefix' => 'fr'],
            ],
        ]]];
    }

    public function validate(array $arguments): ?string
    {
        return $this->configs->problem($arguments, true);
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Configure a site', $this->configs->rows($this->configs->site($arguments, null)), [
            'It is made as a draft: nothing is served until it is activated.',
            'The hosts must also answer at the server (DNS, web server, certificate).',
        ]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        try {
            $site = $this->registry->create($this->configs->site($arguments, null));
        } catch (SiteInvalid $invalid) {
            return CapabilityResult::error(implode(' ', $invalid->problems));
        }

        return CapabilityResult::ok(sprintf('Configured the site "%s" as a draft.', $site->name), ['site' => $this->configs->describe($site)]);
    }
}
