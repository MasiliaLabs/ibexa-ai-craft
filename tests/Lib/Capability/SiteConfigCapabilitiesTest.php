<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Tests\Lib\Capability;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Core\Base\Exceptions\NotFoundException as RepositoryNotFound;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Masilia\Contracts\IbexaSites\Site;
use Masilia\Contracts\IbexaSites\SiteInvalid;
use Masilia\Contracts\IbexaSites\SiteNotFound;
use Masilia\Contracts\IbexaSites\SiteOptions;
use Masilia\Contracts\IbexaSites\SiteRegistry;
use Masilia\Contracts\IbexaSites\SiteSettings;
use Masilia\Contracts\IbexaSites\SiteStatus;
use Masilia\IbexaAiCraft\Capability\SiteConfigCreateCapability;
use Masilia\IbexaAiCraft\Capability\SiteConfigDefaultSiteaccessCapability;
use Masilia\IbexaAiCraft\Capability\SiteConfigDeleteCapability;
use Masilia\IbexaAiCraft\Capability\SiteConfigDescribeCapability;
use Masilia\IbexaAiCraft\Capability\SiteConfigListCapability;
use Masilia\IbexaAiCraft\Capability\SiteConfigSetStatusCapability;
use Masilia\IbexaAiCraft\Capability\SiteConfigUpdateCapability;
use Masilia\IbexaAiCraft\Site\SiteConfigs;
use PHPUnit\Framework\TestCase;

/** The capabilities of site configuration against a registry in memory: what they take, ask first, refuse and answer. */
final class SiteConfigCapabilitiesTest extends TestCase
{
    private object $registry;
    private object $settings;
    private SiteConfigs $configs;

    protected function setUp(): void
    {
        $this->registry = new class implements SiteRegistry {
            /** @var array<string, Site> */
            public array $sites = [];

            public function list(): array
            {
                return array_values($this->sites);
            }

            public function find(string $identifier): ?Site
            {
                return $this->sites[$identifier] ?? null;
            }

            public function problems(Site $site, bool $isNew): array
            {
                $problems = [];
                if ($site->design === 'nope') {
                    $problems[] = 'The design "nope" is not declared.';
                }
                foreach ($this->sites as $other) {
                    if ($other->identifier !== $site->identifier && $other->rootLocationId === $site->rootLocationId) {
                        $problems[] = sprintf('The location %d is already the root of another site.', $site->rootLocationId);
                    }
                }

                return $problems;
            }

            public function create(Site $site): Site
            {
                if (($problems = $this->problems($site, true)) !== []) {
                    throw new SiteInvalid($problems);
                }

                return $this->sites[$site->identifier] = $site;
            }

            public function update(Site $site): Site
            {
                return $this->sites[$site->identifier] = $site;
            }

            public function setStatus(string $identifier, SiteStatus $status): Site
            {
                $site = $this->find($identifier) ?? throw new SiteNotFound($identifier);

                return $this->sites[$identifier] = $site->withStatus($status);
            }

            public function delete(string $identifier): void
            {
                $this->find($identifier) ?? throw new SiteNotFound($identifier);
                unset($this->sites[$identifier]);
            }
        };
        $this->settings = new class implements SiteSettings {
            public ?string $default = null;

            public function defaultSiteaccess(): ?string
            {
                return $this->default;
            }

            public function defaultSiteaccessChoices(): array
            {
                return ['site', 'demo'];
            }

            public function setDefaultSiteaccess(?string $siteaccess): void
            {
                if ($siteaccess !== null && !in_array($siteaccess, $this->defaultSiteaccessChoices(), true)) {
                    throw new SiteInvalid(['not a choice']);
                }
                $this->default = $siteaccess;
            }
        };
        $contents = $this->createMock(ContentService::class);
        $contents->method('loadContentInfo')->willReturnCallback(function (int $id): ContentInfo {
            if ($id !== 77) {
                throw new RepositoryNotFound('content', (string) $id);
            }

            return new ContentInfo(['remoteId' => 'layout77']);
        });
        $repository = $this->createMock(Repository::class);
        $repository->method('getContentService')->willReturn($contents);
        $this->configs = new SiteConfigs($this->registry, $repository);
    }

    /** @return array<string, mixed> */
    private function demo(): array
    {
        return [
            'identifier' => 'demo_group',
            'name' => 'Demo',
            'root_location_id' => 500,
            'layout_id' => 77,
            'design' => 'imal',
            'siteaccesses' => [
                ['name' => 'demo', 'languages' => ['eng-GB'], 'host' => 'Demo.Example.org'],
                ['name' => 'demo_fr', 'languages' => ['fre-FR', 'eng-GB'], 'host' => 'demo.example.org', 'prefix' => '/fr'],
            ],
            'admin_siteaccesses' => [['name' => 'admin_demo', 'languages' => ['eng-GB'], 'host' => 'admin.demo.example.org']],
        ];
    }

    private function create(): SiteConfigCreateCapability
    {
        return new SiteConfigCreateCapability($this->registry, $this->configs);
    }

    public function testASiteIsConfiguredAsADraftFromWhatTheModelGives(): void
    {
        $result = $this->create()->execute($this->demo());

        self::assertTrue($result->success);
        $site = $result->data['site'];
        self::assertSame('draft', $site['status']);
        self::assertSame('layout77', $site['layout_remote_id'], 'the layout is given by its content, the registry keeps its remote id');
        self::assertSame('demo.example.org', $site['siteaccesses'][0]['host'], 'hosts are lower case');
        self::assertSame('fr', $site['siteaccesses'][1]['prefix'], 'a prefix is kept without its slash');
        self::assertSame(['fre-FR', 'eng-GB'], $site['siteaccesses'][1]['languages']);
        self::assertSame('admin_demo', $site['admin_siteaccesses'][0]['name']);
    }

    public function testWhatTheRegistryWouldRefuseIsSaidBeforeTheApproval(): void
    {
        $create = $this->create();

        self::assertNull($create->validate($this->demo()));
        self::assertStringContainsString('design "nope"', (string) $create->validate(['design' => 'nope'] + $this->demo()));
        self::assertStringContainsString('does not exist: it cannot be the layout', (string) $create->validate(['layout_id' => 5] + $this->demo()));
        self::assertStringContainsString('not both', (string) $create->validate(['layout_remote_id' => 'x'] + $this->demo()));

        $create->execute($this->demo());
        self::assertStringContainsString('already exists: use site.config.update', (string) $create->validate($this->demo()));
    }

    public function testTheValuesOfAnEarlierStepOfABatchAreNotCheckedYet(): void
    {
        $arguments = ['root_location_id' => '$site.home_page_location_id', 'layout_id' => '$site.layout_id', 'design' => 'nope'] + $this->demo();

        self::assertNull($this->create()->validate($arguments), 'they are known only when the step runs: the registry decides then');
    }

    public function testARefusalOfTheRegistryIsAnAnswerNotACrash(): void
    {
        $result = $this->create()->execute(['design' => 'nope'] + $this->demo());

        self::assertFalse($result->success);
        self::assertStringContainsString('"nope" is not declared', $result->message);
    }

    public function testAChangeKeepsWhatIsNotGivenAndAnEmptyStringClearsAValue(): void
    {
        $this->create()->execute(['var_dir' => 'var/demo', 'base' => 'main_group'] + $this->demo());
        $update = new SiteConfigUpdateCapability($this->registry, $this->configs);

        $result = $update->execute(['identifier' => 'demo_group', 'name' => 'Renamed', 'var_dir' => '']);

        $site = $result->data['site'];
        self::assertSame('Renamed', $site['name']);
        self::assertNull($site['var_dir'], 'an empty string clears it');
        self::assertSame('main_group', $site['base'], 'what is not given stays');
        self::assertCount(2, $site['siteaccesses']);
        self::assertSame('imal', $site['design']);
        self::assertStringContainsString('No site "ghost"', (string) $update->validate(['identifier' => 'ghost']));
    }

    public function testAStatusIsSetAndAServedSiteIsCheckedAgain(): void
    {
        $this->create()->execute($this->demo());
        $status = new SiteConfigSetStatusCapability($this->registry, $this->configs);

        self::assertNull($status->validate(['identifier' => 'demo_group', 'status' => 'active']));
        self::assertSame('The status is "active" or "disabled".', $status->validate(['identifier' => 'demo_group', 'status' => 'draft']));
        self::assertTrue($status->execute(['identifier' => 'demo_group', 'status' => 'active'])->success);
        self::assertSame(SiteStatus::Active, $this->registry->find('demo_group')?->status);
        self::assertStringContainsString('Anonymous role', implode(' ', $status->preview(['identifier' => 'demo_group', 'status' => 'active'])->notes));
        self::assertFalse($status->execute(['identifier' => 'ghost', 'status' => 'active'])->success);
    }

    public function testTheDefaultSiteaccessIsChosenAmongTheChoices(): void
    {
        $default = new SiteConfigDefaultSiteaccessCapability($this->settings);

        self::assertStringContainsString('Available: site, demo', (string) $default->validate(['siteaccess' => 'ghost']));
        self::assertSame('demo', $default->execute(['siteaccess' => 'demo'])->data['default_siteaccess']);
        self::assertSame('demo', $this->settings->default);
        self::assertNull($default->execute(['siteaccess' => ''])->data['default_siteaccess']);
        self::assertNull($this->settings->default);
    }

    public function testASiteIsListedDescribedAndDeleted(): void
    {
        $this->create()->execute($this->demo());
        $list = new SiteConfigListCapability($this->registry, $this->settings, $this->configs);
        $describe = new SiteConfigDescribeCapability($this->registry, $this->settings, $this->createConfiguredOptions(), $this->configs);
        $delete = new SiteConfigDeleteCapability($this->registry, $this->configs);

        self::assertSame(['demo_group'], array_column($list->execute([])->data['sites'], 'identifier'));
        $described = $describe->execute(['identifier' => 'demo_group'])->data;
        self::assertSame('demo_group', $described['site']['identifier']);
        self::assertSame(['eng-GB', 'fre-FR'], $described['options']['languages']);
        self::assertSame(['site', 'demo'], $described['options']['default_siteaccess_choices']);
        self::assertArrayNotHasKey('site', $describe->execute([])->data, 'without an identifier, only the options');

        self::assertSame(Risk::DESTRUCTIVE, $delete->risk());
        self::assertTrue($delete->execute(['identifier' => 'demo_group'])->success);
        self::assertSame([], $this->registry->list());
        self::assertFalse($delete->execute(['identifier' => 'demo_group'])->success);
    }

    public function testWhatChangesTheServedSitesAsksForTheRolePolicyToo(): void
    {
        $update = new SiteConfigUpdateCapability($this->registry, $this->configs);
        $status = new SiteConfigSetStatusCapability($this->registry, $this->configs);
        $delete = new SiteConfigDeleteCapability($this->registry, $this->configs);

        foreach ([$update, $status, $delete] as $capability) {
            self::assertContains(['role', 'update'], $capability->requires(), $capability->name());
            self::assertContains(['masilia_site', 'manage'], $capability->requires(), $capability->name());
        }
        self::assertSame([['masilia_site', 'manage']], $this->create()->requires(), 'a draft changes no role');
    }

    private function createConfiguredOptions(): SiteOptions
    {
        $options = $this->createMock(SiteOptions::class);
        $options->method('languages')->willReturn(['eng-GB', 'fre-FR']);
        $options->method('designs')->willReturn(['imal']);
        $options->method('baseGroups')->willReturn(['main_group']);
        $options->method('adminSiteaccesses')->willReturn(['admin']);

        return $options;
    }
}
