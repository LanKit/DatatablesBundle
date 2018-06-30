<?php

namespace Tejadong\DatatablesBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('tejadong_datatables');

        $rootNode
            ->children()
                ->arrayNode('datatable')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('use_doctrine_paginator')->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode('service')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('serializer')->defaultValue('jms_serializer.serializer')->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
