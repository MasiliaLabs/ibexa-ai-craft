<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiCraft\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('masilia_ibexa_ai_craft');
        $tree->getRootNode()
            ->children()
                ->arrayNode('conventions')
                    ->info('How the sites of this project are built. The defaults are the Masilia way.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('block_group')->defaultValue('Blocks')->info('The group of content types that are blocks')->end()
                        ->arrayNode('content_groups')
                            ->info('The groups of the other content types the assistant works with')
                            ->scalarPrototype()->end()
                            ->defaultValue(['Content', 'Block items', 'Media', 'Taxonomies', 'Configurations'])
                        ->end()
                        ->scalarNode('page_type')->defaultValue('page')->info('The content type of a page')->end()
                        ->scalarNode('blocks_field')->defaultValue('blocks')->info('The field of a page that holds its blocks')->end()
                        ->scalarNode('items_field')->defaultValue('items')->info('The field of a block that holds its child items')->end()
                        ->scalarNode('site_type')->defaultValue('site')->info('The content type of the container of a site, made by site.create_structure (fields title, domain, description, favicon)')->end()
                        ->scalarNode('home_page_type')->defaultValue('home_page')->info('The content type of the home page of a site (title and the blocks field)')->end()
                        ->scalarNode('layout_type')->defaultValue('layout_config')->info('The content type of the layout configuration of a site (field bo_title)')->end()
                        ->scalarNode('folder_type')->defaultValue('folder')->info('The content type of a folder (field name)')->end()
                    ->end()
                ->end()
                ->booleanNode('theme_creation')
                    ->info('Whether theme.create may write the files of a new theme into the project. Off by default: a deployment is not the place to write templates.')
                    ->defaultFalse()
                ->end()
            ->end();

        return $tree;
    }
}
