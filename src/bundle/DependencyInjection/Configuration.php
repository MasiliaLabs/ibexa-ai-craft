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
                    ->end()
                ->end()
            ->end();

        return $tree;
    }
}
