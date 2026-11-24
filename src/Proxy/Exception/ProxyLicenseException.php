<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception;

/**
 * The proxy rejected the transmitted license (HTTP 401/403 with a license_* error code).
 */
class ProxyLicenseException extends ProxyException
{
}
