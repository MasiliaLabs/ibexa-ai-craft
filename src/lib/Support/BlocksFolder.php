<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Support;

/**
 * The folder that holds the blocks of a page, a child of that page: "<name of the page> Blocks". Its remote id is the one of
 * the page and "_blocks". The same rules as Masilia\IbexaBlocks\Content\BlocksFolder (masilia/ibexa-blocks 0.6), kept here so
 * that this package keeps accepting the older versions of that one.
 */
final class BlocksFolder
{
    public const REMOTE_ID_SUFFIX = '_blocks';

    public static function name(string $pageName, string $suffix = 'Blocks'): string
    {
        $pageName = trim($pageName);

        return $pageName === '' ? $suffix : $pageName . ' ' . $suffix;
    }

    public static function remoteId(string $pageRemoteId): string
    {
        return $pageRemoteId . self::REMOTE_ID_SUFFIX;
    }

    /** Whether a folder is named like a folder of blocks ("About Blocks", or just "Blocks") */
    public static function nameMatches(string $folderName, string $suffix = 'Blocks'): bool
    {
        $folderName = trim($folderName);

        return strcasecmp($folderName, $suffix) === 0 || str_ends_with(strtolower($folderName), ' ' . strtolower($suffix));
    }
}
