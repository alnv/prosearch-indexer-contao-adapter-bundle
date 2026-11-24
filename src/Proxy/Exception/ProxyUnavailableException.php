<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception;

/**
 * The proxy is not reachable (network, TLS, timeout) or answered with 502/503/504.
 */
class ProxyUnavailableException extends ProxyException
{
}
