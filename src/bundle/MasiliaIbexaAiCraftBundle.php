<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiCraft;

use Masilia\Bundle\IbexaAiCraft\DependencyInjection\Compiler\SharedConventionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * A package on the Ibexa AI OS (masilia/ibexa-ai): it teaches the assistant how sites are built here. Its capabilities
 * and its skill are registered by implementing the interfaces of the OS; nothing is wired by hand.
 */
final class MasiliaIbexaAiCraftBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new SharedConventionsPass());
    }
}
