<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Adapter;

use GuzzleHttp\Client;

class Proxy
{

    protected string $strProxyDomain = "https://elasticsearch.sineos.de/proxy";

    protected string $strLicence = "";

    public function __construct($strLicence)
    {
        $this->strLicence = $strLicence;
    }

    public function indexDocument($arrParams)
    {

        if (!$this->strLicence) {
            return false;
        }

        $objClient = new Client();
        $objClient->request('POST', $this->strProxyDomain . '/search/index?licence=' . $this->strLicence, [
            'json' => [
                'body' => $arrParams
            ]
        ]);

        return true;
    }

    public function deleteDocument($strIndex, $strDocumentId)
    {

        if (!$this->strLicence) {
            return false;
        }

        $objClient = new Client();
        $objClient->request('POST', $this->strProxyDomain . '/search/delete?licence=' . $this->strLicence, [
            'json' => [
                'body' => [
                    'index' => $strIndex,
                    'id' => $strDocumentId
                ]
            ]
        ]);

        return true;
    }

    public function indexMapping($arrParams)
    {

        if (!$this->strLicence) {
            return false;
        }

        $client = new Client();
        $client->request('POST', $this->strProxyDomain . '/search/mapping?licence=' . $this->strLicence, [
            'json' => [
                'body' => $arrParams
            ]
        ]);

        return true;
    }

    public function search($arrKeywords, $strIndex, $arrOptions)
    {

        if (!$this->strLicence) {
            return false;
        }

        $client = new Client();
        $res = $client->request('POST', $this->strProxyDomain . '/search/results?licence=' . $this->strLicence, [
            'json' => [
                'keywords' => $arrKeywords,
                'options' => $arrOptions,
                'index' => $strIndex,
            ]
        ]);

        return json_decode($res->getBody()->getContents(), true);
    }

    public function autocompletion($arrKeywords, $strIndex, $arrOptions)
    {

        if (!$this->strLicence) {
            return false;
        }

        $client = new Client();
        $res = $client->request('POST', $this->strProxyDomain . '/search/autocompletion?licence=' . $this->strLicence, [
            'json' => [
                'keywords' => $arrKeywords,
                'options' => $arrOptions,
                'index' => $strIndex,
            ]
        ]);

        return json_decode($res->getBody()->getContents(), true);
    }
}