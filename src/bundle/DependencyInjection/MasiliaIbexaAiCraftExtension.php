<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiCraft\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class MasiliaIbexaAiCraftExtension extends Extension
{
    public function getAlias(): string
    {
        return 'masilia_ibexa_ai_craft';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $container->setParameter('ibexa_ai_craft.conventions', $config['conventions']);
        $container->setParameter('ibexa_ai_craft.theme_creation', $config['theme_creation']);

        (new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config')))->load('services.yaml');
    }
}
