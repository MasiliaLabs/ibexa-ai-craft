<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAiCraft\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * How a site is built (the page type, the field of its blocks…) is defined once. When masilia/ibexa-blocks is installed, its
 * conventions are the ones the assistant follows: it builds pages the way the front office renders them. Without it, this
 * bundle's own settings are used.
 */
final class SharedConventionsPass implements CompilerPassInterface
{
    private const SHARED = 'masilia_blocks.conventions';
    private const OWN = 'ibexa_ai_craft.conventions';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::SHARED)) {
            return;
        }

        $shared = (array) $container->getParameter(self::SHARED);
        $own = $container->hasParameter(self::OWN) ? (array) $container->getParameter(self::OWN) : [];
        $container->setParameter(self::OWN, array_replace($own, array_intersect_key($shared, array_flip(['page_type', 'blocks_field', 'items_field', 'items_field_by_type', 'block_group', 'content_groups']))));
    }
}
