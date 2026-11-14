<?php

declare(strict_types=1);

namespace Masilia\IbexaAiCraft\Tests\Bundle\DependencyInjection;

use Masilia\Bundle\IbexaAiCraft\DependencyInjection\Compiler\SharedConventionsPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SharedConventionsPassTest extends TestCase
{
    public function testTheConventionsOfTheFrontOfficeAreTheOnesTheAssistantFollows(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('ibexa_ai_craft.conventions', ['page_type' => 'page', 'blocks_field' => 'blocks']);
        $container->setParameter('masilia_blocks.conventions', ['page_type' => 'landing', 'blocks_field' => 'sections', 'items_field' => 'cards', 'unknown' => 'x']);

        (new SharedConventionsPass())->process($container);

        self::assertSame(['page_type' => 'landing', 'blocks_field' => 'sections', 'items_field' => 'cards'], $container->getParameter('ibexa_ai_craft.conventions'));
    }

    public function testWithoutTheFrontOfficePackageTheOwnSettingsStand(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('ibexa_ai_craft.conventions', ['page_type' => 'page']);

        (new SharedConventionsPass())->process($container);

        self::assertSame(['page_type' => 'page'], $container->getParameter('ibexa_ai_craft.conventions'));
    }
}
