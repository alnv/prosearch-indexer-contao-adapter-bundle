<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Alnv\ProSearchIndexerContaoAdapterBundle\AlnvProSearchIndexerContaoAdapterBundle;
use Symfony\Component\HttpKernel\KernelInterface;

class Plugin implements BundlePluginInterface, RoutingPluginInterface
{

    public function getBundles(ParserInterface $parser): array
    {

        return [
            BundleConfig::create(AlnvProSearchIndexerContaoAdapterBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class])
                ->setReplace(['prosearch-indexer-contao-adapter-bundle']),
        ];
    }

    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel)
    {

        $strRoutingYmlFile = 'routing.yml';
        if (\version_compare(ContaoCoreBundle::getVersion(), '5.4.0', '>=')) {
            $strRoutingYmlFile = 'routing_c5.yml';
        }

        return $resolver
            ->resolve(__DIR__ . '/../Resources/config/' . $strRoutingYmlFile)
            ->load(__DIR__ . '/../Resources/config/' . $strRoutingYmlFile);
    }
}