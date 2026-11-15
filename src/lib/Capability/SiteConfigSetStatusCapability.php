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
use Masilia\Contracts\IbexaSites\SiteStatus;
use Masilia\IbexaAiCraft\Site\SiteConfigs;

final class SiteConfigSetStatusCapability extends AbstractCapability
{
    public function __construct(private readonly SiteRegistry $registry, private readonly SiteConfigs $configs)
    {
    }

    public function name(): string
    {
        return 'site.config.set_status';
    }

    public function summary(): string
    {
        return 'Serve a site (active) or stop serving it (disabled).';
    }

    public function description(): string
    {
        return 'Activates a site of the registry: its siteaccesses answer on their hosts, and visitors may log in (the Anonymous role is updated). Or disables it: they stop answering, and the contents stay. '
            . 'An active site must be valid: its hosts, root and design are checked again. The siteaccess that is the default one cannot be disabled.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['identifier', 'status'],
            'properties' => [
                'identifier' => ['type' => 'string', 'description' => 'The site (site.config.list)'],
                'status' => ['type' => 'string', 'enum' => ['active', 'disabled']],
            ],
        ];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['masilia_site', 'manage'], ['role', 'update']];
    }

    public function examples(): array
    {
        return [['description' => 'Serve a site', 'arguments' => ['identifier' => 'demo_group', 'status' => 'active']]];
    }

    public function validate(array $arguments): ?string
    {
        $site = $this->registry->find(trim((string) ($arguments['identifier'] ?? '')));
        if ($site === null) {
            return sprintf('No site "%s" in the registry (site.config.list lists them).', (string) ($arguments['identifier'] ?? ''));
        }
        $status = SiteStatus::tryFrom((string) ($arguments['status'] ?? ''));
        if ($status === null || $status === SiteStatus::Draft) {
            return 'The status is "active" or "disabled".';
        }
        $problems = $status->isServed() ? $this->registry->problems($site->withStatus($status), false) : [];

        return $problems === [] ? null : implode(' ', $problems);
    }

    public function preview(array $arguments): Preview
    {
        $site = $this->registry->find(trim((string) ($arguments['identifier'] ?? '')));
        $serve = ($arguments['status'] ?? '') === 'active';

        return new Preview($serve ? 'Serve a site' : 'Stop serving a site', $site === null ? [['Site', (string) ($arguments['identifier'] ?? '')]] : $this->configs->rows($site), $serve
            ? ['Its siteaccesses answer on their hosts at once, and the Anonymous role is updated so that visitors can log in.']
            : ['Its siteaccesses stop answering (their hosts fall back to the default siteaccess). The contents stay.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        try {
            $site = $this->registry->setStatus(trim((string) ($arguments['identifier'] ?? '')), SiteStatus::from((string) $arguments['status']));
        } catch (SiteInvalid $invalid) {
            return CapabilityResult::error(implode(' ', $invalid->problems));
        } catch (SiteNotFound) {
            return CapabilityResult::error(sprintf('No site "%s" in the registry.', (string) ($arguments['identifier'] ?? '')));
        }

        return CapabilityResult::ok(sprintf('The site "%s" is %s.', $site->name, $site->status->value), ['site' => $this->configs->describe($site)]);
    }
}
