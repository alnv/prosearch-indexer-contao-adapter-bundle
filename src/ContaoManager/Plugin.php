<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\ContaoManager;

use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class Plugin implements BundlePluginInterface, RoutingPluginInterface
{

    public function getBundles(ParserInterface $parser)
    {

        return [
            BundleConfig::create('Alnv\ProSearchIndexerContaoAdapterBundle\AlnvProSearchIndexerContaoAdapterBundle')
                ->setLoadAfter(['Contao\CoreBundle\ContaoCoreBundle'])
                ->setReplace(['prosearch-indexer-contao-adapter-bundle']),
        ];
    }

    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel)
    {

        return $resolver
            ->resolve(__DIR__ . '/../Resources/config/routing.yml')
            ->load(__DIR__ . '/../Resources/config/routing.yml');
    }
}