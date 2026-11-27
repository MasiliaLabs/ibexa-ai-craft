<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Site;

use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\IbexaAiCraft\Support\BlocksFolder;
use Masilia\IbexaAiCraft\Support\Conventions;
use Masilia\IbexaAiCraft\Support\ConventionsSource;
use RuntimeException;

/**
 * The folder of the blocks of a page (or of the home page): found by its remote id (the one of the page and "_blocks"), then by
 * its name when someone made it by hand, and made, as a child of the page, when it is missing. The page must be a container.
 */
final readonly class BlocksFolders
{
    /** The field of a folder that holds its name. */
    private const FOLDER_NAME = 'name';

    public function __construct(
        private Repository $repository,
        private ConventionsSource $conventionsSource,
    ) {
    }

    /**
     * The folder of the blocks of a page, when its content type can hold one (it is a container); null otherwise, so that building
     * a site on a project whose home page is not a container still works (without that folder).
     */
    public function ensureIfPossible(int $pageContentId, ?string $language = null): ?int
    {
        $info = $this->repository->getContentService()->loadContentInfo($pageContentId);
        if (!$info->getContentType()->isContainer()) {
            return null;
        }

        return $this->ensure($pageContentId, $language);
    }

    /**
     * @return int The main location of the folder
     */
    public function ensure(int $pageContentId, ?string $language = null): int
    {
        $contents = $this->repository->getContentService();
        $locations = $this->repository->getLocationService();
        $page = $contents->loadContentInfo($pageContentId);
        $remoteId = BlocksFolder::remoteId((string) $page->remoteId);

        try {
            $existing = $contents->loadContentInfoByRemoteId($remoteId)->mainLocationId;
            if ($existing !== null) {
                return (int) $existing;
            }
        } catch (NotFoundException) {
        }
        $language ??= (string) $page->mainLanguageCode;

        if ($page->mainLocationId === null) {
            throw new RuntimeException('The content has no location.');
        }
        $parent = $locations->loadLocation((int) $page->mainLocationId);
        foreach ($locations->loadLocationChildren($parent, 0, 100) as $child) {
            if ($child->contentInfo->getContentType()->identifier === $this->conventionsSource->conventions()->folderType
                && BlocksFolder::nameMatches((string) $child->getContent()->getName(), $this->conventionsSource->conventions()->blocksFolderSuffix)) {
                return (int) $child->id;
            }
        }

        $struct = $contents->newContentCreateStruct($this->repository->getContentTypeService()->loadContentTypeByIdentifier($this->conventionsSource->conventions()->folderType), $language);
        $struct->remoteId = $remoteId;
        $struct->setField(self::FOLDER_NAME, BlocksFolder::name((string) $page->name, $this->conventionsSource->conventions()->blocksFolderSuffix), $language);
        $published = $contents->publishVersion($contents->createContent($struct, [$locations->newLocationCreateStruct($parent->id)])->getVersionInfo());

        return (int) $published->contentInfo->getMainLocation()->id;
    }
}
