<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Capability;

use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Masilia\Contracts\IbexaSites\SiteOptions;
use Masilia\Contracts\IbexaSites\SiteRegistry;
use Masilia\Contracts\IbexaSites\SiteSettings;
use Masilia\IbexaAiCraft\Site\SiteConfigs;

final class SiteConfigDescribeCapability extends AbstractCapability
{
    public function __construct(
        private readonly SiteRegistry $registry,
        private readonly SiteSettings $settings,
        private readonly SiteOptions $options,
        private readonly SiteConfigs $configs,
    ) {
    }

    public function name(): string
    {
        return 'site.config.describe';
    }

    public function summary(): string
    {
        return 'What a site can be set to (languages, designs, base groups, admin siteaccesses), and one site of the registry when named.';
    }

    public function description(): string
    {
        return 'Gives the options for site.config.create and update: the language codes Ibexa has, the designs the project declares, the groups a site can take its settings from, the admin siteaccesses of the YAML, and the siteaccesses that can be the default. '
            . 'With an identifier it also gives that site as it is. Use it before creating a site, to choose among what exists.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['identifier' => ['type' => 'string', 'description' => 'A site of the registry (its group name, "demo_group")']]];
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
        return [['description' => 'The options', 'arguments' => []], ['description' => 'One site', 'arguments' => ['identifier' => 'demo_group']]];
    }

    public function validate(array $arguments): ?string
    {
        $identifier = trim((string) ($arguments['identifier'] ?? ''));

        return $identifier !== '' && $this->registry->find($identifier) === null ? sprintf('No site "%s" in the registry (site.config.list lists them).', $identifier) : null;
    }

    public function execute(array $arguments): CapabilityResult
    {
        $data = [
            'options' => [
                'languages' => $this->options->languages(),
                'designs' => $this->options->designs(),
                'base_groups' => $this->options->baseGroups(),
                'admin_siteaccesses' => $this->options->adminSiteaccesses(),
                'default_siteaccess_choices' => $this->settings->defaultSiteaccessChoices(),
            ],
            'default_siteaccess' => $this->settings->defaultSiteaccess(),
        ];
        $identifier = trim((string) ($arguments['identifier'] ?? ''));
        if ($identifier !== '' && ($site = $this->registry->find($identifier)) !== null) {
            $data['site'] = $this->configs->describe($site);
        }

        return CapabilityResult::ok($identifier === '' ? 'The options of a site.' : sprintf('The site "%s" and the options of a site.', $identifier), $data);
    }
}
