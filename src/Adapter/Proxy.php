<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Adapter;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Authorization;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Logger;
use GuzzleHttp\Client;
use Psr\Log\LogLevel;

class Proxy
{
    protected string $strProxyDomain = "https://elasticsearch.sineos.de/proxy";

    protected string $strLicence = "";

    public function __construct($strLicence)
    {
        $this->strLicence = $strLicence ?: "";

        if (!(new Authorization())->isValid($this->strLicence)) {
            $this->strLicence = '';
        }
    }

    public function indexDocument($arrParams): bool
    {
        if (!$this->strLicence) {
            return false;
        }

        try {
            $objClient = new Client();
            $response = $objClient->request('POST', $this->strProxyDomain . '/search/index?licence=' . $this->strLicence, [
                'json' => [
                    'body' => $arrParams
                ],
                'timeout' => 10,
                'connect_timeout' => 10,
            ]);

            $state = \json_decode($response->getBody()->getContents(), true);

            if (isset($state['error'])) {
                return false;
            }

        } catch (\Exception $e) {
            Logger::set($e->getMessage(), LogLevel::ERROR, __CLASS__ . '::' . __FUNCTION__);
            return false;
        }

        return true;
    }

    public function deleteDatabase($strIndex): bool
    {
        $objClient = new Client();
        $response = $objClient->request('POST', $this->strProxyDomain . '/delete/database/' . $strIndex);

        $state = \json_decode($response->getBody()->getContents(), true);
        if (isset($state['error'])) {
            return false;
        }

        return true;
    }

    public function deleteDocument($strIndex, $strDocumentId): bool
    {
        $objClient = new Client();
        $response = $objClient->request('POST', $this->strProxyDomain . '/search/delete', [
            'json' => [
                'body' => [
                    'index' => $strIndex,
                    'id' => $strDocumentId
                ]
            ],
            'timeout' => 10,
            'connect_timeout' => 10
        ]);

        $state = \json_decode($response->getBody()->getContents(), true);
        if (isset($state['error'])) {
            return false;
        }

        return true;
    }

    public function indexMapping($arrParams): bool
    {
        if (!$this->strLicence) {
            return false;
        }

        try {
            $client = new Client();
            $response = $client->request('POST', $this->strProxyDomain . '/search/mapping?licence=' . $this->strLicence, [
                'json' => [
                    'body' => $arrParams
                ],
                'timeout' => 15,
                'connect_timeout' => 10
            ]);

            $state = \json_decode($response->getBody()->getContents(), true);
            if (isset($state['error'])) {
                return false;
            }

        } catch (\Exception $e) {
            Logger::set($e->getMessage(), LogLevel::ERROR, __CLASS__ . '::' . __FUNCTION__);
            return false;
        }

        return true;
    }

    public function search($arrKeywords, $strIndex, $arrOptions)
    {
        if (!$this->strLicence) {
            return false;
        }

        try {
            $client = new Client();
            $res = $client->request('POST', $this->strProxyDomain . '/search/results?licence=' . $this->strLicence, [
                'json' => [
                    'keywords' => $arrKeywords,
                    'options' => $arrOptions,
                    'index' => $strIndex,
                ],
                'timeout' => 30,
                'connect_timeout' => 30
            ]);

            return json_decode($res->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Logger::set($e->getMessage(), LogLevel::ERROR, __CLASS__ . '::' . __FUNCTION__);
            return false;
        }
    }

    public function autocompletion($arrKeywords, $strIndex, $arrOptions)
    {
        if (!$this->strLicence) {
            return false;
        }

        try {
            $client = new Client();
            $res = $client->request('POST', $this->strProxyDomain . '/search/autocompletion?licence=' . $this->strLicence, [
                'json' => [
                    'keywords' => $arrKeywords,
                    'options' => $arrOptions,
                    'index' => $strIndex,
                ],
                'timeout' => 10,
                'connect_timeout' => 10
            ]);

            return json_decode($res->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Logger::set($e->getMessage(), LogLevel::ERROR, __CLASS__ . '::' . __FUNCTION__);
            return false;
        }
    }
}