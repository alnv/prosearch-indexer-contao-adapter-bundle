<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\AI;

use Contao\FrontendTemplate;
use Alnv\ContaoOpenAiAssistantBundle\Library\Parser;
use Alnv\ContaoOpenAiAssistantBundle\Helpers\Toolkit;
use Alnv\ProSearchIndexerContaoAdapterBundle\Entity\Result;
use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;

class AiChatComponentParser extends Parser
{

    public function getAdditionalInstructions(): string
    {

        return 'Du bist ein intelligenter Assistent, der darauf spezialisiert ist, präzise und informative Antworten auf gestellte Fragen zu geben. Du kannst gerne die URL zur Seite in deine Antwort einbauen.
            Deine Aufgabe ist es, den Vektor-Store nach relevanten Informationen zu durchsuchen, die Frage umfassend zu beantworten und die Quellen der verwendeten Informationen im JSON-Format anzugeben.
            Formuliere eine prägnante Antwort und stelle sicher, dass du keine direkten Quellenangaben in deinem Text machst. 
            Stattdessen, hänge die Quellen im folgenden JSON-Format an: ' .
            '
            ```json
            {
                "pages": [
                    {
                        "URL": "https://",
                        "title": "…"
                    },
                    {
                        "URL": "https://",
                        "title": "…"
                    }
                ]
            }
            ```
            ' .
            'Falls möglich, präsentiere deine Antwort in Form einer HTML-Tabelle oder Liste, um die Informationen klar und übersichtlich darzustellen. Beachte folgende Reihenfolge: Antworte erst mit einem kurzen einleitenden Satz, dann gebe das JSON aus und dann kommt die präzise und informative Antwort auf gestellte Fragen.';
    }

    public function parseMessages($strMessage, $arrMessages, $arrOptions = []): string
    {

        $strHits = "";
        $arrPages = Toolkit::getJsonFromMessage($strMessage, '<code class="json">', '</code>');

        if (empty($arrPages)) {
            $arrPages = Toolkit::getJsonFromMessage($strMessage, '<code>', '</code>');
        }

        foreach (($arrPages['pages'] ?? []) as $arrPage) {
            $strPageURL = $arrPage['URL'] ?? '';
            if (!$strPageURL) {
                continue;
            }

            $objDocument = IndicesModel::findByUrl($strPageURL);
            if (!$objDocument) {
                continue;
            }

            $objEntity = new Result();
            $objEntity->addHit($objDocument->id, []);
            $arrResult = $objEntity->getResult();

            if ($arrResult) {
                $objTemplate = new FrontendTemplate('elasticsearch_result');
                $objTemplate->setData($arrResult);
                $strHits .= $objTemplate->parse();
            }
        }

        if ($strHits) {
            $strMessage = Toolkit::replace($strMessage, '<code class="json">', '</code>', $strHits);
            $strMessage = str_replace('<code class="json">', '<div class="elasticsearch-results">', $strMessage);
            $strMessage = str_replace('</code>', '</div>', $strMessage);
            $strMessage = str_replace('<pre>', '', $strMessage);
            $strMessage = str_replace('</pre>', '', $strMessage);
        }

        return $strMessage;
    }
}