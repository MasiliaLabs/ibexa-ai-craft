<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Capability;

use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Masilia\Contracts\IbexaSites\SiteInvalid;
use Masilia\Contracts\IbexaSites\SiteSettings;

final class SiteConfigDefaultSiteaccessCapability extends AbstractCapability
{
    public function __construct(private readonly SiteSettings $settings)
    {
    }

    public function name(): string
    {
        return 'site.config.default_siteaccess';
    }

    public function summary(): string
    {
        return 'Choose the siteaccess that answers when no host matches (or go back to the project\'s).';
    }

    public function description(): string
    {
        return 'Sets the default siteaccess of the platform: what is served for a host that no siteaccess knows. Choose among the front siteaccesses of the project and of the active sites (site.config.describe lists them); empty goes back to the YAML\'s. '
            . 'The site that holds it can no longer be disabled or deleted.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['siteaccess'], 'properties' => ['siteaccess' => ['type' => 'string', 'description' => 'A siteaccess name, or an empty string for the project\'s default']]];
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
        return [['description' => 'Serve a site for the hosts nobody knows', 'arguments' => ['siteaccess' => 'demo']], ['description' => 'Back to the project\'s default', 'arguments' => ['siteaccess' => '']]];
    }

    public function validate(array $arguments): ?string
    {
        $name = trim((string) ($arguments['siteaccess'] ?? ''));

        return $name !== '' && !in_array($name, $this->settings->defaultSiteaccessChoices(), true)
            ? sprintf('"%s" can not be the default siteaccess. Available: %s.', $name, implode(', ', $this->settings->defaultSiteaccessChoices()))
            : null;
    }

    public function preview(array $arguments): Preview
    {
        $name = trim((string) ($arguments['siteaccess'] ?? ''));

        return new Preview('Choose the default siteaccess', [['Now', (string) ($this->settings->defaultSiteaccess() ?? 'the project\'s (YAML)')], ['Then', $name === '' ? 'the project\'s (YAML)' : $name]], ['It answers for every host that no siteaccess knows.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $name = trim((string) ($arguments['siteaccess'] ?? ''));
        try {
            $this->settings->setDefaultSiteaccess($name === '' ? null : $name);
        } catch (SiteInvalid $invalid) {
            return CapabilityResult::error(implode(' ', $invalid->problems));
        }

        return CapabilityResult::ok($name === '' ? 'The default siteaccess is the project\'s again.' : sprintf('The default siteaccess is "%s".', $name), ['default_siteaccess' => $name === '' ? null : $name]);
    }
}
