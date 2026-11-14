<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Craft builds on the public API of masilia/ibexa-ai, Masilia\Contracts\IbexaAi, so that a change inside the OS cannot
 * break it: its code names no other class of the OS. (Its tests may: they run against the real implementations.)
 */
final class ArchitectureTest extends TestCase
{
    private const SRC = __DIR__ . '/../src';

    /** What it still takes from the inside of the OS, file by file: nothing, and it stays so. */
    private const STILL_INTERNAL = [];

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

    public function testCraftTakesFromTheBlocksOnlyItsContracts(): void
    {
        $wrong = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::SRC, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $code = (string) preg_replace('#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents($file->getPathname()));
            if (preg_match_all('/\bMasilia\\(?:Bundle\\)?IbexaBlocks\\[A-Za-z0-9_\\]+/', $code, $names) > 0) {
                $wrong[] = sprintf('%s uses %s: take it from Masilia\\Contracts\\IbexaBlocks (or add it there).', substr($file->getPathname(), strlen(self::SRC) + 1), implode(', ', array_unique($names[0])));
            }
        }

        self::assertSame([], $wrong, implode("\n", $wrong));
    }

    /** What a bundle may hold: the integration, nothing else. */
    private const BUNDLE_MAY_HOLD = ['Command', 'DependencyInjection', 'Resources', 'MasiliaIbexaAiCraftBundle.php'];

    public function testTheBundleHoldsTheSymfonyIntegrationOnly(): void
    {
        $found = array_values(array_diff(scandir(self::SRC . '/bundle') ?: [], ['.', '..']));

        self::assertSame([], array_values(array_diff($found, self::BUNDLE_MAY_HOLD)), 'The bundle holds what is not integration: it belongs in src/lib.');
    }

    public function testTheLibKnowsNeitherTheBundleNorTheWiringOfSymfony(): void
    {
        $forbidden = ['Masilia\\Bundle\\', 'Symfony\\Component\\HttpKernel\\', 'Symfony\\Bundle\\', 'Symfony\\Component\\Config\\', 'Symfony\\Component\\DependencyInjection\\ContainerBuilder', 'Symfony\\Component\\DependencyInjection\\Extension\\', 'Symfony\\Component\\DependencyInjection\\Compiler\\', 'Symfony\\Component\\HttpFoundation\\'];
        $wrong = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::SRC . '/lib', FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            preg_match_all('/^use ([A-Za-z0-9_\\\\]+)/m', (string) file_get_contents($file->getPathname()), $uses);
            foreach ($uses[1] as $name) {
                foreach ($forbidden as $prefix) {
                    if (str_starts_with($name, $prefix)) {
                        $wrong[] = sprintf('%s uses %s', substr($file->getPathname(), strlen(self::SRC) + 1), $name);
                    }
                }
            }
        }

        self::assertSame([], $wrong, implode("\n", $wrong));
    }
}
