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

    public function parseDomain(string $strDomain): string
    {

        $arrFragments = parse_url($strDomain) ?? [];
        $strHost = $arrFragments['host'] ?? '';
        $strPort = $arrFragments['port'] ?? '';

        if (!$strHost) {
            return $strDomain;
        }

        $strHost = str_replace('www.', '', $strHost);

        return trim($strHost) . ($strPort ? ':' . $strPort : '');
    }

    public function pluckKeyFromKeysGlobalByDomain(array $arrKeys, string $strDomain): string
    {

        $strDomain = $this->parseDomain($strDomain);

        foreach ($arrKeys as $arrKey) {

            if ($arrKey['domain'] == $strDomain) {

                return $arrKey['key'];
            }
        }

        return '';
    }

    public function isValid(string $strLicenseIdentifier): bool
    {
        if (!$strLicenseIdentifier) {
            return false;
        }

        $arrLicenseFragments = $this->decodeLicense($strLicenseIdentifier);

        if (!$arrLicenseFragments['license']) {
            return false;
        }

        if (in_array($arrLicenseFragments['license'], ['ck-23-kiel', 'alpha-test'])) {
            return true;
        }

        $strRootDir = System::getContainer()->getParameter('kernel.project_dir');
        $strCacheFolder = $strRootDir . '/var/cache/authorization';

        if (!file_exists($strCacheFolder)) {
            mkdir($strCacheFolder, 0777, true);
        }

        $strFileContent = file_get_contents($strCacheFolder . '/auth.txt', true);

        if ($strFileContent == $strLicenseIdentifier) {
            return true;
        }

        try {
            $objClient = new Client();
            $request = new Request('GET', $this->strUrl . '/' . $this->strMethod . $arrLicenseFragments['license'], [
                'Authorization' => 'Bearer ' . ($arrLicenseFragments['authToken'] ?? '')
            ], '');
            $objResponse = $objClient->send($request);
            $arrJsonReturn = \json_decode($objResponse->getBody()->getContents(), true);
            $blnValid = $arrJsonReturn['valid'] ?? false;
            $strDomain = $arrJsonReturn['domain'] ?? '';
            if ($blnValid === true && $strDomain == $arrLicenseFragments['domain']) {
                $strFilename = $strCacheFolder . '/auth.txt';
                file_put_contents($strFilename, $strLicenseIdentifier, FILE_APPEND | LOCK_EX);
                return true;
            }

        } catch (\Exception $objError) {
            System::getContainer()
                ->get('monolog.logger.contao')
                ->log(LogLevel::ERROR, 'Prosearch Lizenz: ' . $objError->getMessage(), ['contao' => new ContaoContext(__CLASS__ . '::' . __FUNCTION__)]);
        }

        return false;
    }

    public function encodeLicense($strLicense, $strDomain, $strAuthToken = ''): string
    {
        return rtrim(strtr(base64_encode($strLicense . '::' . $strDomain . '::' . $strAuthToken), '+/', '-_'), '=');
    }

    public function decodeLicense($strLicenseIdentifier): array
    {
        $strFragments = rtrim(strtr(base64_decode($strLicenseIdentifier), '+/', '-_'), '=');
        $arrFragments = explode('::', $strFragments);

        return [
            'license' => $arrFragments[0] ?? '',
            'domain' => $arrFragments[1] ?? '',
            'authToken' => $arrFragments[2] ?? ''
        ];
    }
}