<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\System;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Psr\Log\LogLevel;

class Authorization
{
    private string $strUrl = "https://app.sineos.de";

    private string $strMethod = 'api/license/check/';

    public function parseDomain(string $domain): string
    {
        $hasScheme = \parse_url($domain, PHP_URL_SCHEME) !== null;
        $urlToParse = $hasScheme ? $domain : 'https://' . $domain;

        $arrFragments = \parse_url($urlToParse) ?? [];
        $host = $arrFragments['host'] ?? '';
        $port = $arrFragments['port'] ?? '';

        if (!$host) {
            return $domain;
        }

        $host = \preg_replace('/^www\./i', '', $host);

        return trim($host) . ($port ? ':' . $port : '');
    }

    public function pluckKeyFromKeysGlobalByDomain(array $keys, string $domain): string
    {
        $domain = $this->parseDomain($domain);

        foreach ($keys as $key) {
            if ($this->parseDomain($key['domain']) == $domain) {
                return $key['key'];
            }
        }

        return $keys[0]['key'] ?? '';
    }

    /**
     *
     * Diese Lizenzen nur für Testzwecke verwenden!
     */
    private function testKeys4DevOnly($strLicense): bool
    {
        return in_array($strLicense, ['ck-23-kiel', 'alpha-test']);
    }

    public function isValid(string $strLicenseIdentifier): bool
    {
        if (!$strLicenseIdentifier) {
            return false;
        }

        if ($this->testKeys4DevOnly($strLicenseIdentifier)) {
            return true;
        }

        $licenseFragments = $this->decodeLicense($strLicenseIdentifier);
        if (!($licenseFragments['license'] ?? '')) {
            return false;
        }

        if ($this->testKeys4DevOnly($licenseFragments['license'])) {
            return true;
        }

        $strFileContent = '';
        $strRootDir = System::getContainer()->getParameter('kernel.project_dir');
        $cacheFolder = $strRootDir . '/var/authorization';

        if (!file_exists($cacheFolder)) {
            \mkdir($cacheFolder, 0777, true);
        }

        if (\file_exists($cacheFolder . '/auth.txt')) {
            $strFileContent = \file_get_contents($cacheFolder . '/auth.txt');
        }

        if ($strFileContent == $strLicenseIdentifier) {
            return true;
        }

        try {
            $objClient = new Client();
            $request = new Request('GET', $this->strUrl . '/' . $this->strMethod . $licenseFragments['license'], [
                'Authorization' => 'Bearer ' . ($licenseFragments['authToken'] ?? ''),
                'Accept' => 'application/json',
            ], '');

            $objResponse = $objClient->send($request);
            $arrJsonReturn = \json_decode($objResponse->getBody()->getContents(), true);

            $valid = $arrJsonReturn['valid'] ?? false;
            $domain = $arrJsonReturn['domain'] ?? '';

            if ($valid === true && $domain == $licenseFragments['domain']) {
                $strFilename = $cacheFolder . '/auth.txt';

                \file_put_contents($strFilename, $strLicenseIdentifier, LOCK_EX);

                return true;
            }

        } catch (\Exception $objError) {
            Logger::set($objError->getMessage(), LogLevel::ERROR, __CLASS__ . '::' . __FUNCTION__);
        }

        return false;
    }

    public function encodeLicense($strLicense, $strDomain, $strAuthToken = ''): string
    {
        return rtrim(strtr(base64_encode($strLicense . '::' . $strDomain . '::' . $strAuthToken), '+/', '-_'), '=');
    }

    public function decodeLicense(string $strLicenseIdentifier): array
    {
        $b64 = \strtr($strLicenseIdentifier, '-_', '+/');
        $remainder = \strlen($b64) % 4;
        if ($remainder) {
            $b64 .= \str_repeat('=', 4 - $remainder);
        }

        $strFragments = \base64_decode($b64, true);
        if ($strFragments === false) {
            return ['license' => '', 'domain' => '', 'authToken' => ''];
        }

        $arrFragments = \explode('::', $strFragments);

        return [
            'license' => $arrFragments[0] ?? '',
            'domain' => $arrFragments[1] ?? '',
            'authToken' => $arrFragments[2] ?? ''
        ];
    }
}