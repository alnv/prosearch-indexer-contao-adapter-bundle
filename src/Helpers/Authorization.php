<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\LicenseProvider;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\ProxyClient;
use Contao\System;
use Psr\Log\LogLevel;

/**
 * Former license check. The license logic now lives exclusively in the ProSearch proxy
 * (alnv/prosearch-proxy-bundle, License\LicenseChecker). This class only remains for
 * backwards compatibility: identifier helpers delegate to {@see LicenseProvider}, isValid() asks the proxy.
 *
 * @deprecated since 4.0, will be removed in 5.0: use LicenseProvider and ProxyClient::getLicenseStatus().
 */
class Authorization
{
    public function parseDomain(string $domain): string
    {
        return $this->provider()->parseDomain($domain);
    }

    public function pluckKeyFromKeysGlobalByDomain(array $keys, string $domain): string
    {
        return $this->provider()->pluckKeyFromKeysGlobalByDomain($keys, $domain);
    }

    /**
     * Asks the proxy whether the license identifier is valid (no local license logic).
     */
    public function isValid(string $strLicenseIdentifier): bool
    {
        if (!$strLicenseIdentifier) {
            return false;
        }

        try {
            return true === (System::getContainer()->get(ProxyClient::class)->getLicenseStatus($strLicenseIdentifier)['valid'] ?? false);
        } catch (ProxyException $e) {
            Logger::set($e->getMessage(), LogLevel::ERROR, __METHOD__);

            return false;
        }
    }

    public function encodeLicense($strLicense, $strDomain, $strAuthToken = ''): string
    {
        return $this->provider()->encodeLicense((string) $strLicense, (string) $strDomain, (string) $strAuthToken);
    }

    /**
     * Decodes an identifier into its fragments (format helper, no validation).
     */
    public function decodeLicense(string $strLicenseIdentifier): array
    {
        $b64 = strtr($strLicenseIdentifier, '-_', '+/');
        $remainder = \strlen($b64) % 4;

        if ($remainder) {
            $b64 .= str_repeat('=', 4 - $remainder);
        }

        $fragments = base64_decode($b64, true);

        if (false === $fragments) {
            return ['license' => '', 'domain' => '', 'authToken' => ''];
        }

        $fragments = explode('::', $fragments);

        return ['license' => $fragments[0] ?? '', 'domain' => $fragments[1] ?? '', 'authToken' => $fragments[2] ?? ''];
    }

    private function provider(): LicenseProvider
    {
        $container = System::getContainer();

        return null !== $container && $container->has(LicenseProvider::class) ? $container->get(LicenseProvider::class) : new LicenseProvider();
    }
}