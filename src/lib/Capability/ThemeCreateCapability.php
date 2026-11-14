<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Capability;

use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Masilia\Contracts\IbexaBlocks\Themes;

/** Writes files of the project: it is off until the project says it may (masilia_ibexa_ai_craft.theme_creation). */
final class ThemeCreateCapability extends AbstractCapability
{
    public function __construct(
        private readonly Themes $themes,
        private readonly bool $enabled = false,
    ) {
    }

    public function name(): string
    {
        return 'theme.create';
    }

    public function summary(): string
    {
        return 'Create a theme on another one: its manifest, its layout and the sources of its assets.';
    }

    public function description(): string
    {
        return 'Writes the files of a new theme (theme.yaml, layout.twig, the stylesheet and script sources) that builds on an existing theme and shows pages as it does. '
            . 'It writes files of the project, so it is only available when the project allows it. The answer lists what is left to do by hand (declare the theme in a design, build the assets).';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['name'], 'properties' => [
            'name' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_]*$', 'description' => 'Lower case letters, digits and _'],
            'extends' => ['type' => 'string', 'description' => 'The theme it builds on (default "standard")'],
        ]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['setup', 'administrate']];
    }

    public function examples(): array
    {
        return [['description' => 'A theme for a new site', 'arguments' => ['name' => 'blog', 'extends' => 'standard']]];
    }

    public function validate(array $arguments): ?string
    {
        return $this->enabled ? null : 'Creating themes is turned off for this project (masilia_ibexa_ai_craft.theme_creation).';
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Create a theme', [['Name', (string) $arguments['name']], ['Builds on', (string) ($arguments['extends'] ?? 'standard')]], [
            'Files of the project are written: templates/themes/<name>/ and assets/themes/<name>/. Nothing is overwritten.',
        ]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        try {
            $created = $this->themes->create((string) $arguments['name'], (string) ($arguments['extends'] ?? 'standard'));
        } catch (\InvalidArgumentException $e) {
            return CapabilityResult::error($e->getMessage());
        }

        return CapabilityResult::ok(sprintf('Theme "%s" created (%d files).', $arguments['name'], count($created['files'])), $created);
    }
}
