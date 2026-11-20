<?php

declare(strict_types=1);

namespace Masilia\Tests\IbexaAiCraft\Lib\Site;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException as ContractsNotFound;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentCreateStruct;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\LocationCreateStruct;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Masilia\IbexaAiCraft\Site\BlocksFolders;
use Masilia\IbexaAiCraft\Support\BlocksFolder;
use Masilia\IbexaAiCraft\Support\Conventions;
use PHPUnit\Framework\TestCase;

final class BlocksFoldersTest extends TestCase
{
    public function testTheRulesNameTheFolderAfterThePage(): void
    {
        $this->assertSame('About Blocks', BlocksFolder::name('About'));
        $this->assertSame('about_page_blocks', BlocksFolder::remoteId('about_page'));
        $this->assertTrue(BlocksFolder::nameMatches('Home Blocks'));
        $this->assertFalse(BlocksFolder::nameMatches('Media'));
    }

    public function testTheFolderIsFoundByItsRemoteId(): void
    {
        $contents = $this->createStub(ContentService::class);
        $contents->method('loadContentInfo')->willReturn($this->info(7, 'about_page', 'About', 70));
        $contents->method('loadContentInfoByRemoteId')->willReturnCallback(function (string $remoteId): ContentInfo {
            $this->assertSame('about_page_blocks', $remoteId);

            return $this->info(8, 'about_page_blocks', 'About Blocks', 80);
        });

        $this->assertSame(80, (new BlocksFolders($this->repository($contents, $this->createStub(LocationService::class)), new Conventions()))->ensure(7, 'eng-GB'));
    }

    public function testAFolderMadeByHandIsFoundByItsName(): void
    {
        $contents = $this->createStub(ContentService::class);
        $contents->method('loadContentInfo')->willReturn($this->info(7, 'about_page', 'About', 70));
        $contents->method('loadContentInfoByRemoteId')->willThrowException(new NotFoundException('content', 'x'));
        $childInfo = $this->info(9, 'by_hand', 'About Blocks', 90, 'folder');
        $child = new class($childInfo, $this->content('About Blocks')) extends \Ibexa\Core\Repository\Values\Content\Location {
            public function __construct(ContentInfo $info, private Content $folderContent)
            {
                parent::__construct(['id' => 90, 'contentInfo' => $info]);
            }

            public function getContent(): Content
            {
                return $this->folderContent;
            }
        };
        $locations = $this->createStub(LocationService::class);
        $locations->method('loadLocation')->willReturn(new \Ibexa\Core\Repository\Values\Content\Location(['id' => 70]));
        $locations->method('loadLocationChildren')->willReturn(new \Ibexa\Contracts\Core\Repository\Values\Content\LocationList(['locations' => [$child], 'totalCount' => 1]));

        $this->assertSame(90, (new BlocksFolders($this->repository($contents, $locations), new Conventions()))->ensure(7, 'eng-GB'));
    }

    public function testNoFolderForAContentThatCannotHoldOne(): void
    {
        $contents = $this->createStub(ContentService::class);
        $contents->method('loadContentInfo')->willReturn($this->info(7, 'home', 'Home', 70, 'home_page', false));

        $this->assertNull((new BlocksFolders($this->repository($contents, $this->createStub(LocationService::class)), new Conventions()))->ensureIfPossible(7, 'eng-GB'));
    }

    private function repository(ContentService $contents, LocationService $locations): Repository
    {
        $repository = $this->createStub(Repository::class);
        $repository->method('getContentService')->willReturn($contents);
        $repository->method('getLocationService')->willReturn($locations);
        $repository->method('getContentTypeService')->willReturn($this->createStub(ContentTypeService::class));

        return $repository;
    }

    private function info(int $id, string $remoteId, string $name, int $mainLocationId, string $type = 'page', bool $container = true): ContentInfo
    {
        $info = new ContentInfo(['id' => $id, 'remoteId' => $remoteId, 'name' => $name, 'mainLocationId' => $mainLocationId, 'contentType' => new \Ibexa\Core\Repository\Values\ContentType\ContentType(['identifier' => $type, 'isContainer' => $container])]);

        return $info;
    }

    private function content(string $name): Content
    {
        $content = $this->createStub(Content::class);
        $content->method('getName')->willReturn($name);

        return $content;
    }
}
