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

final class SiteConfigDeleteCapability extends AbstractCapability
{
    public function __construct(private readonly SiteRegistry $registry, private readonly SiteConfigs $configs)
    {
    }

    public function name(): string
    {
        return 'site.config.delete';
    }

    public function summary(): string
    {
        return 'Delete the configuration of a site of the registry (its siteaccesses stop existing; its contents stay).';
    }

    public function description(): string
    {
        return 'Deletes a site of the registry: its siteaccesses, hosts and design setting go, the contents (pages, layout, media) are not touched. The site that holds the default siteaccess cannot be deleted. Prefer site.config.set_status with "disabled" to stop serving a site for a while.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['identifier'], 'properties' => ['identifier' => ['type' => 'string', 'description' => 'The site (site.config.list)']]];
    }

    public function risk(): Risk
    {
        return Risk::DESTRUCTIVE;
    }

    public function requires(): array
    {
        return [['masilia_site', 'manage'], ['role', 'update']];
    }

    public function examples(): array
    {
        return [['description' => 'Delete the configuration of a site', 'arguments' => ['identifier' => 'demo_group']]];
    }

    public function validate(array $arguments): ?string
    {
        return $this->registry->find(trim((string) ($arguments['identifier'] ?? ''))) === null ? sprintf('No site "%s" in the registry (site.config.list lists them).', (string) ($arguments['identifier'] ?? '')) : null;
    }

    public function preview(array $arguments): Preview
    {
        $site = $this->registry->find(trim((string) ($arguments['identifier'] ?? '')));

        return new Preview('Delete the configuration of a site', $site === null ? [['Site', (string) ($arguments['identifier'] ?? '')]] : $this->configs->rows($site), ['The siteaccesses stop existing; the contents of the site are not deleted. This cannot be undone: the configuration would have to be made again.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $identifier = trim((string) ($arguments['identifier'] ?? ''));
        try {
            $this->registry->delete($identifier);
        } catch (SiteInvalid $invalid) {
            return CapabilityResult::error(implode(' ', $invalid->problems));
        } catch (SiteNotFound) {
            return CapabilityResult::error(sprintf('No site "%s" in the registry.', $identifier));
        }

        return CapabilityResult::ok(sprintf('Deleted the configuration of "%s".', $identifier), ['identifier' => $identifier]);
    }
}
