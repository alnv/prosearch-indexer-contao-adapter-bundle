<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\AI;

use Alnv\ContaoOpenAiAssistantBundle\Chat\Agent;
use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Contao\StringUtil;

class AiElasticsearch
{

    protected string $strAssistant;

    protected array $arrOptions;

    public function __construct($strAssistant, $arrOptions = [])
    {
        $this->strAssistant = $strAssistant;
        $this->arrOptions = $arrOptions;
    }

    public function getHits($strPrompt): array
    {

        if (!$strPrompt) {
            return [];
        }

        $arrOptions = [
            'additional_instructions' => 'Durchsuche den Vektor-Store nach passenden Dokumenten und gebe die gefundene URL zu den jeweiligen Dokumenten Komma getrennt aus. Gib mir nur die Komma-getrennte Liste an URLs, sonst nichts.'
        ];

        $strURLs = '';
        $arrHits = [];
        $objAgent = new Agent($this->strAssistant, $arrOptions);
        $objAgent->addMessage($strPrompt);

        for ($i=0;$i<20;$i++) {
            usleep(500000);
            $arrMessages = $objAgent->getMessages();
            if (!empty($arrMessages)) {
                $strURLs = $arrMessages['data'][0]['content'][0]['text']['value'] ?? '0';
                break;
            }
        }

        if ($strURLs) {

            foreach (explode(',', $strURLs) as $strPageUrl) {

                if (!$strPageUrl || !is_string($strPageUrl)) {
                    continue;
                }

                $strPageUrl = trim($strPageUrl);
                $objDocument = IndicesModel::findByUrl($strPageUrl);

                if (!$objDocument) {
                    continue;
                }

                if ($objDocument->state != States::ACTIVE) {
                    continue;
                }

                $arrHits[] = [
                    'sort' => [],
                    'highlight' => [],
                    '_source' => [
                        'id' => $objDocument->id,
                        'types' => StringUtil::deserialize($objDocument->types, true)
                    ]
                ];
            }
        }

        return $arrHits;
    }
}