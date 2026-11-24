<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('alnv_pro_search_indexer_contao_adapter');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('proxy')
                    ->addDefaultsIfNotSet()
                    ->info('Connection to the ProSearch proxy (alnv/prosearch-proxy-bundle)')
                    ->children()
                        ->scalarNode('url')->defaultValue('%env(PROSEARCH_PROXY_URL)%')->info('Base URL without /api/v1')->end()
                        ->scalarNode('api_key')->defaultValue('%env(PROSEARCH_PROXY_API_KEY)%')->info('Optional client API key (X-Api-Key)')->end()
                        ->scalarNode('verify_tls')->defaultValue('%env(bool:PROSEARCH_PROXY_VERIFY_TLS)%')->end()
                        ->floatNode('timeout')->defaultValue(30.0)->end()
                        ->scalarNode('search_profile')->defaultValue('prosearch_default')->info('Search profile of the proxy used by the front end search')->end()
                    ->end()
                ->end()
                ->arrayNode('indexing')
                    ->addDefaultsIfNotSet()
                    ->info('Document capture (crawler) and chunking')
                    ->children()
                        ->integerNode('chunk_size')->defaultValue(600)->min(1)->info('Maximum number of tokens per chunk')->end()
                        ->integerNode('chunk_overlap')->defaultValue(60)->min(0)->info('Tokens shared by consecutive chunks')->end()
                        ->integerNode('tokenizer_piece_length')->defaultValue(5)->min(1)->info('Characters per token of the built-in tokenizer (approximation of subword tokenizers)')->end()
                        ->integerNode('max_text_bytes')->defaultValue(1000000)->min(1000)->info('Longer document texts are truncated (OpenSearch highlights at most 1,000,000 characters per field)')->end()
                        ->integerNode('pdf_max_file_size')->defaultValue(5 * 1024 * 1024)->min(1024)->info('PDF files above this size (bytes) are not parsed')->end()
                        ->integerNode('pdf_max_pages')->defaultValue(0)->min(0)->info('Only the first n pages of a PDF are read, 0 = all')->end()
                        ->integerNode('pdf_decode_memory_limit')->defaultValue(64 * 1024 * 1024)->min(0)->info('Memory limit (bytes) for decompressing single PDF streams, 0 = unlimited')->end()
                    ->end()
                ->end()
                ->arrayNode('queue')
                    ->addDefaultsIfNotSet()
                    ->info('Asynchronous transfer of tl_indices to the proxy (cron job / prosearch:index:sync)')
                    ->children()
                        ->scalarNode('cron_enabled')->defaultValue('%env(bool:PROSEARCH_QUEUE_CRON)%')->info('Transfer by cron job; disable on staging copies that share the license (and therefore the index) with production. The commands keep working.')->end()
                        ->integerNode('batch_size')->defaultValue(50)->min(1)->max(1000)->info('Documents claimed per batch')->end()
                        ->integerNode('max_request_bytes')->defaultValue(8 * 1024 * 1024)->min(65536)->info('Maximum size of one bulk request to the proxy (proxy default limit: 10 MB)')->end()
                        ->integerNode('max_attempts')->defaultValue(5)->min(1)->info('Failed transfers of a document before it is marked as failed')->end()
                        ->integerNode('retry_delay')->defaultValue(60)->min(1)->info('Seconds before the first retry, doubled for every further attempt')->end()
                        ->integerNode('max_retry_delay')->defaultValue(3600)->min(1)->info('Upper limit of the retry delay (seconds)')->end()
                        ->integerNode('claim_ttl')->defaultValue(300)->min(30)->info('Seconds a claimed batch is reserved for one worker (crashed workers release it after that)')->end()
                        ->floatNode('cron_time_limit')->defaultValue(20.0)->min(1.0)->info('Seconds the minutely cron job may spend on transfers')->end()
                        ->integerNode('engine_ttl')->defaultValue(86400)->min(60)->info('Seconds before the cached search engine detection is refreshed')->end()
                    ->end()
                ->end()
                ->arrayNode('stats')
                    ->addDefaultsIfNotSet()
                    ->info('Search statistics dashboard')
                    ->children()
                        ->integerNode('log_retention_days')->defaultValue(0)->min(0)->info('Days the search statistics (tl_search_stats_log) are kept, 0 = forever')->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}