<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Capability;

use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Masilia\Contracts\IbexaBlocks\Themes;

final class ThemeListCapability extends AbstractCapability
{
    public function __construct(private readonly Themes $themes)
    {
    }

    public function name(): string
    {
        return 'theme.list';
    }

    public function summary(): string
    {
        return 'The designs of the front office and the themes each is made of.';
    }

    public function description(): string
    {
        return 'Lists the designs (the admin excluded) with their themes, the design\'s own first: a theme can override the templates of the ones after it. Use theme.check to see whether a design is sound.';
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
        return [['setup', 'administrate']];
    }

    public function examples(): array
    {
        return [['description' => 'The designs', 'arguments' => []]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $designs = $this->themes->designs();

        return CapabilityResult::ok(sprintf('%d design(s).', count($designs)), ['designs' => $designs]);
    }
}
