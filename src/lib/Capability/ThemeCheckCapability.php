<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Capability;

use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Masilia\Contracts\IbexaBlocks\Themes;

final class ThemeCheckCapability extends AbstractCapability
{
    public function __construct(private readonly Themes $themes)
    {
    }

    public function name(): string
    {
        return 'theme.check';
    }

    public function summary(): string
    {
        return 'Check a design: block types no theme shows, missing templates or asset sources, dead files.';
    }

    public function description(): string
    {
        return 'Checks the themes of a design the way masilia:blocks:theme:check does. An "error" means a page can break (for example a block type that no theme shows answers 404); a "notice" is a clean-up.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['design'], 'properties' => ['design' => ['type' => 'string', 'description' => 'A design, as theme.list gives it']]];
    }

    public function risk(): Risk
    {
        return Risk::READ;
    }

    public function requires(): array
    {
        return [['setup', 'administrate']];
    }

    public function examples(): array
    {
        return [['description' => 'Check the design of a site', 'arguments' => ['design' => 'acme']]];
    }

    public function validate(array $arguments): ?string
    {
        return isset($this->themes->designs()[(string) $arguments['design']]) ? null : sprintf('There is no design "%s": see theme.list.', $arguments['design']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $issues = $this->themes->check((string) $arguments['design']);
        $errors = count(array_filter($issues, static fn (array $issue): bool => $issue['level'] === 'error'));

        return CapabilityResult::ok(sprintf('%d error(s), %d notice(s).', $errors, count($issues) - $errors), ['issues' => $issues]);
    }
}
