<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Tests\Bundle\DependencyInjection;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\Bundle\IbexaAiCraft\DependencyInjection\BlocksConventions;
use Masilia\Bundle\IbexaBlocks\Site\SettingsProvider;
use PHPUnit\Framework\TestCase;

final class BlocksConventionsTest extends TestCase
{
    /** @param array<string, mixed> $parameters */
    private function blocks(array $parameters): SettingsProvider
    {
        return new SettingsProvider(new class($parameters) implements ConfigResolverInterface {
            /** @param array<string, mixed> $p */
            public function __construct(private array $p)
            {
            }

            public function getParameter(string $paramName, ?string $namespace = null, ?string $scope = null): mixed
            {
                return $this->p[$paramName];
            }

            public function hasParameter(string $paramName, ?string $namespace = null, ?string $scope = null): bool
            {
                return array_key_exists($paramName, $this->p);
            }

            public function setDefaultNamespace(string $defaultNamespace): void
            {
            }

            public function getDefaultNamespace(): string
            {
                return 'masilia_ibexa_blocks';
            }
        });
    }

    public function testTheConventionsOfTheFrontOfficeAreTheOnesOfTheAssistant(): void
    {
        $c = (new BlocksConventions(['page_type' => 'page', 'layout_type' => 'my_layout'], $this->blocks([
            'conventions.page_type' => 'landing', 'conventions.items_field_by_type' => ['info_cards' => 'cards'],
        ])))->conventions();

        self::assertSame('landing', $c->pageType);
        self::assertSame('cards', $c->itemsFieldFor('info_cards'));
        self::assertSame('my_layout', $c->layoutType, 'what the front office does not know stays the assistant\'s own');
    }

    public function testTheSiteOfTheRequestDecidesAtEachCall(): void
    {
        $source = new BlocksConventions([], $this->blocks(['conventions.blocks_field' => 'sections']));

        self::assertSame('sections', $source->conventions()->blocksField);
    }

    public function testWithoutTheFrontOfficePackageTheOwnSettingsStand(): void
    {
        $c = (new BlocksConventions(['page_type' => 'article']))->conventions();

        self::assertSame('article', $c->pageType);
    }
}
