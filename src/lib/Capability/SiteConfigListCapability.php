<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Capability;

use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Masilia\Contracts\IbexaSites\SiteRegistry;
use Masilia\Contracts\IbexaSites\SiteSettings;
use Masilia\IbexaAiCraft\Site\SiteConfigs;

final class SiteConfigListCapability extends AbstractCapability
{
    public function __construct(private readonly SiteRegistry $registry, private readonly SiteSettings $settings, private readonly SiteConfigs $configs)
    {
    }

    public function name(): string
    {
        return 'site.config.list';
    }

    public function summary(): string
    {
        return 'The sites created at run time (their siteaccesses, hosts, languages, design) and the default siteaccess.';
    }

    public function description(): string
    {
        return 'Lists the sites of the registry: status (draft, active, disabled), root location, design, siteaccesses with their languages, host and prefix, admin siteaccesses. '
            . 'The sites declared in the project\'s YAML are not in it. The answer also has default_siteaccess (null: the YAML\'s).';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function risk(): Risk
    {
        return Risk::READ;
    }

    public function requires(): array
    {
        return [['masilia_site', 'view']];
    }

    public function examples(): array
    {
        return [['description' => 'The sites of the registry', 'arguments' => []]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $sites = array_map($this->configs->describe(...), $this->registry->list());

        return CapabilityResult::ok(sprintf('%d site(s).', count($sites)), ['sites' => $sites, 'default_siteaccess' => $this->settings->defaultSiteaccess()]);
    }
}
