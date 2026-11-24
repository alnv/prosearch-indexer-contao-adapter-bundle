<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class AlnvProSearchIndexerContaoAdapterExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        foreach ([
                     'PROSEARCH_PROXY_URL' => 'https://elasticsearch.sineos.de',
                     'PROSEARCH_PROXY_API_KEY' => '',
                     'PROSEARCH_PROXY_VERIFY_TLS' => 'true',
                     'PROSEARCH_QUEUE_CRON' => 'true',
                 ] as $env => $default) {
            if (!$container->hasParameter('env(' . $env . ')')) {
                $container->setParameter('env(' . $env . ')', $default);
            }
        }

        $config = $this->processConfiguration(new Configuration(), $configs);

        foreach ($config['proxy'] as $key => $value) {
            $container->setParameter('prosearch_adapter.proxy.' . $key, $value);
        }

        $container->setParameter('prosearch_adapter.stats.log_retention_days', $config['stats']['log_retention_days']);

        if ($config['indexing']['chunk_overlap'] >= $config['indexing']['chunk_size']) {
            throw new \InvalidArgumentException('alnv_pro_search_indexer_contao_adapter.indexing.chunk_overlap must be smaller than chunk_size.');
        }

        foreach (['indexing', 'queue'] as $section) {
            foreach ($config[$section] as $key => $value) {
                $container->setParameter('prosearch_adapter.' . $section . '.' . $key, $value);
            }
        }

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
    }
}
