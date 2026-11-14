<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Craft builds on the public API of masilia/ibexa-ai, Masilia\Contracts\IbexaAi, so that a change inside the OS cannot
 * break it. The list below is what it still takes from the inside of the OS, file by file, until its tools are services
 * of its own (phase 4 of the rework): it only gets shorter. A new use of the inside fails, and so does a line of the list
 * that is no longer true.
 */
final class ArchitectureTest extends TestCase
{
    private const SRC = __DIR__ . '/../src/bundle';

    private const STILL_INTERNAL = [
        'Capability/CreateStructureCapability.php' => ['Masilia\\Bundle\\IbexaAi\\Agent\\Tool\\ToolInterface', 'Masilia\\Bundle\\IbexaAi\\Os\\Capability\\BaseCapability'],
        'Catalog/BlockCatalog.php' => ['Masilia\\Bundle\\IbexaAi\\Support\\FieldType'],
        'Site/ItemsCreator.php' => ['Masilia\\Bundle\\IbexaAi\\Agent\\Tool\\ContentFactoryInterface', 'Masilia\\IbexaAi\\Suggestion\\LanguageCode'],
        'Site/SiteExplorer.php' => ['Masilia\\Bundle\\IbexaAi\\Agent\\Tool\\ToolRegistry', 'Masilia\\Bundle\\IbexaAi\\Agent\\Tool\\ToolResult'],
        'Tool/BrowseSiteStructureTool.php' => ['Masilia\\Bundle\\IbexaAi\\Agent\\Tool\\AgentErrorHelper', 'Masilia\\Bundle\\IbexaAi\\Agent\\Tool\\ToolInterface', 'Masilia\\Bundle\\IbexaAi\\Agent\\Tool\\ToolResult', 'Masilia\\Bundle\\IbexaAi\\Support\\AiConstants'],
        'Tool/CreateSiteStructureTool.php' => ['Masilia\\Bundle\\IbexaAi\\Agent\\Tool\\AgentErrorHelper', 'Masilia\\Bundle\\IbexaAi\\Agent\\Tool\\ContentCreator', 'Masilia\\Bundle\\IbexaAi\\Agent\\Tool\\ContentUpdater', 'Masilia\\Bundle\\IbexaAi\\Agent\\Tool\\ImageFileHelper', 'Masilia\\Bundle\\IbexaAi\\Agent\\Tool\\ToolInterface', 'Masilia\\Bundle\\IbexaAi\\Agent\\Tool\\ToolResult', 'Masilia\\Bundle\\IbexaAi\\Support\\AiConstants'],
        'Tool/FindParentCandidatesTool.php' => ['Masilia\\Bundle\\IbexaAi\\Agent\\Tool\\AgentErrorHelper', 'Masilia\\Bundle\\IbexaAi\\Agent\\Tool\\ToolInterface', 'Masilia\\Bundle\\IbexaAi\\Agent\\Tool\\ToolResult'],
        'Tool/ListBlockTypesTool.php' => ['Masilia\\Bundle\\IbexaAi\\Agent\\Tool\\ToolInterface', 'Masilia\\Bundle\\IbexaAi\\Agent\\Tool\\ToolResult'],
    ];

    public function testCraftTakesFromTheOsOnlyItsContractsAndWhatTheListStillAllows(): void
    {
        $found = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::SRC, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $code = (string) preg_replace('#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents($file->getPathname()));
            preg_match_all('/\b(Masilia\\\\(?:Bundle\\\\)?IbexaAi\\\\[A-Za-z0-9_\\\\]+)/', $code, $names);
            $internal = array_values(array_unique(array_map(static fn (string $name): string => rtrim($name, '\\'), $names[1])));
            sort($internal);
            if ($internal !== []) {
                $found[substr($file->getPathname(), strlen(self::SRC) + 1)] = $internal;
            }
        }
        ksort($found);

        $new = $stale = [];
        foreach ($found as $path => $names) {
            foreach (array_diff($names, self::STILL_INTERNAL[$path] ?? []) as $name) {
                $new[] = sprintf('%s uses %s: take it from Masilia\\Contracts\\IbexaAi (or add it there).', $path, $name);
            }
        }
        foreach (self::STILL_INTERNAL as $path => $names) {
            foreach (array_diff($names, $found[$path] ?? []) as $name) {
                $stale[] = sprintf('%s no longer uses %s: remove it from STILL_INTERNAL.', $path, $name);
            }
        }

        self::assertSame([], [...$new, ...$stale], implode("\n", [...$new, ...$stale]));
    }
}
