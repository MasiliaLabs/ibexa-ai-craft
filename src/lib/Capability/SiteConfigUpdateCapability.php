<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Capability;

use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Masilia\Contracts\IbexaSites\SiteInvalid;
use Masilia\Contracts\IbexaSites\SiteNotFound;
use Masilia\Contracts\IbexaSites\SiteRegistry;
use Masilia\IbexaAiCraft\Site\SiteConfigs;

final class SiteConfigUpdateCapability extends AbstractCapability
{
    public function __construct(private readonly SiteRegistry $registry, private readonly SiteConfigs $configs)
    {
    }

    public function name(): string
    {
        return 'site.config.update';
    }

    public function summary(): string
    {
        return 'Change the configuration of a site of the registry: only what is given changes.';
    }

    public function description(): string
    {
        return 'Changes a site of the registry. Give the identifier and only what changes; a list (siteaccesses, admin_siteaccesses) given replaces the whole list. '
            . 'An empty string clears base, var_dir, layout_remote_id, session_cookie_domain and admin_siteaccess. '
            . 'The status does not change here (site.config.set_status). A site that is served is changed at once.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['identifier'],
            'properties' => ['identifier' => ['type' => 'string', 'description' => 'The site to change (site.config.list)']] + SiteConfigs::properties(),
        ];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        // A site that is served keeps visitors in through the Anonymous role: changing its siteaccesses updates it.
        return [['masilia_site', 'manage'], ['role', 'update']];
    }

    public function examples(): array
    {
        return [['description' => 'Rename a site and give it a directory for its files', 'arguments' => ['identifier' => 'demo_group', 'name' => 'Demo site', 'var_dir' => 'var/demo']]];
    }

    public function validate(array $arguments): ?string
    {
        return $this->configs->problem($arguments, false);
    }

    public function preview(array $arguments): Preview
    {
        $current = $this->registry->find(trim((string) ($arguments['identifier'] ?? '')));
        $site = $this->configs->site($arguments, $current);

        return new Preview('Change a site', $this->configs->rows($site), $site->status->isServed()
            ? ['The site is served: the change is live at once, and the Anonymous role is updated if its siteaccesses change.']
            : ['The site is not served: the change takes effect when it is activated.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        try {
            $current = $this->registry->find(trim((string) ($arguments['identifier'] ?? ''))) ?? throw new SiteNotFound((string) ($arguments['identifier'] ?? ''));
            $site = $this->registry->update($this->configs->site($arguments, $current));
        } catch (SiteInvalid $invalid) {
            return CapabilityResult::error(implode(' ', $invalid->problems));
        } catch (SiteNotFound) {
            return CapabilityResult::error(sprintf('No site "%s" in the registry.', (string) ($arguments['identifier'] ?? '')));
        }

        return CapabilityResult::ok(sprintf('Changed the site "%s".', $site->name), ['site' => $this->configs->describe($site)]);
    }
}
