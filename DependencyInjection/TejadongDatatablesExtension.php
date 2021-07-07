<?php

namespace Tejadong\DatatablesBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class TejadongDatatablesExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        foreach ($config['service'] as $key => $service) {
            $container->setAlias($this->getAlias() . '.' . $key, $service);
        }

        $container->getDefinition('tejadong_datatables')
            ->replaceArgument(2, $config['datatable']['use_doctrine_paginator']);
    }

    /**
     * {@inheritDoc}
     */
    public function getAlias()
    {
        return 'tejadong_datatables';
    }
}
