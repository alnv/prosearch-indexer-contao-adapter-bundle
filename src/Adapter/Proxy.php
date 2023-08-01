<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Adapter;

use GuzzleHttp\Client;

class Proxy
{

    protected string $strProxyDomain = "http://airtec:8888/proxy";

    protected string $strLicence = "";

    public function __construct($strLicence)
    {
        $this->strLicence = $strLicence;
    }

    public function indexDocument($arrParams)
    {

        $objClient = new Client();
        $objClient->request('POST', $this->strProxyDomain . '/search/index?licence=' . $this->strLicence, [
            'json' => [
                'body' => $arrParams
            ]
        ]);
    }

    public function deleteDocument($strIndex, $strDocumentId)
    {

        $objClient = new Client();
        $objClient->request('POST', $this->strProxyDomain . '/search/delete?licence=' . $this->strLicence, [
            'json' => [
                'body' => [
                    'index' => $strIndex,
                    'id' => $strDocumentId
                ]
            ]
        ]);
    }

    public function indexMapping($arrParams)
    {

        $client = new Client();
        $client->request('POST', $this->strProxyDomain . '/search/mapping?licence=' . $this->strLicence, [
            'json' => [
                'body' => $arrParams
            ]
        ]);
    }

    public function search($arrKeywords, $strIndex, $arrOptions)
    {

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