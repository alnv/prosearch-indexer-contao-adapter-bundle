<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\Signature;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\EngineCapabilities;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception\ProxyException;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\LicenseProvider;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\ProxyClient;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\MappingFactory;
use Contao\Database;
use Contao\DataContainer;
use Contao\DC_Table;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;

$GLOBALS['TL_DCA']['tl_search_credentials'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'enableVersioning' => true,
        'backendSearchIgnore' => true,
        'onload_callback' => [
            function () {
                $strRequestToken = System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue();
                $objEntity = Database::getInstance()->prepare('SELECT * FROM tl_search_credentials ORDER BY id DESC')->limit(1)->execute();
                if ($objEntity->numRows) {
                    if (!$objEntity->signature) {
                        Database::getInstance()->prepare('UPDATE tl_search_credentials %s WHERE id=?')->set([
                            'signature' => Signature::generate()
                        ])->limit(1)->execute($objEntity->id);
                    }
                    if (!Input::get('act') && !Input::get('id')) {
                        $this->redirect($this->addToUrl('act=edit&id=' . $objEntity->id . '&rt=' . $strRequestToken));
                    }
                } else {
                    if (!Input::get('act')) {
                        $this->redirect($this->addToUrl('act=create' . '&rt=' . $strRequestToken));
                    }
                }
            }
        ],
        'onsubmit_callback' => [function (DataContainer $dataContainer) {
            // Connection and license are checked by the ProSearch proxy, the adapter only transmits the data.
            $container = System::getContainer();
            $licenseProvider = $container->get(LicenseProvider::class);
            $licenseProvider->reset();

            if (!$licenseProvider->hasCredentials() || 'licence' !== ($licenseProvider->getCredentials()['type'] ?? '')) {
                return;
            }

            $proxy = $container->get(ProxyClient::class);

            try {
                $health = $proxy->health();

                if (($health['status'] ?? '') !== 'ok') {
                    Message::addError(sprintf('ProSearch-Proxy %s ist erreichbar, aber das Such-Backend meldet einen Fehler.', $proxy->getBaseUrl()));
                } else {
                    Message::addConfirmation(sprintf('Verbindung zum ProSearch-Proxy %s hergestellt (%s %s).', $proxy->getBaseUrl(), $health['backend']['distribution'] ?? $health['backend']['type'] ?? '', $health['backend']['version'] ?? ''));
                }

                $license = $proxy->getLicenseStatus($licenseProvider->getLicenseIdentifier());

                if (!($license['valid'] ?? false)) {
                    Message::addError('ProSearch-Proxy: ' . ($license['message'] ?? 'Lizenz ungültig'));
                }
            } catch (ProxyException $exception) {
                Message::addError(sprintf('Keine Verbindung zum ProSearch-Proxy %s: %s', $proxy->getBaseUrl(), $exception->getMessage()));
            }

            $engine = $container->get(EngineCapabilities::class)->refresh();

            if ('' !== $engine->error) {
                Message::addError('Suchserver konnte nicht erkannt werden, Chunks werden nicht übertragen: ' . $engine->error);
            } else {
                Message::addInfo(sprintf('Suchserver: %s – Chunks (semantische Suche): %s.', $engine->describe(), $engine->supportsChunks ? 'ja' : 'nein (nur OpenSearch 3 oder neuer)'));
            }
        }],
        'sql' => [
            'keys' => [
                'id' => 'primary'
            ]
        ]
    ],
    'list' => [
        'sorting' => [
            'mode' => 0
        ],
        'label' => [
            'fields' => ['type'],
            'showColumns' => true
        ],
        'operations' => [
            'edit' => [
                'primary' => true,
                'icon' => 'header.svg',
                'href' => 'act=edit'
            ],
            'delete' => [
                'primary' => true,
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? '') . '\'))return false;Backend.getScrollOffset()"'
            ],
            'show' => [
                'primary' => true,
                'href' => 'act=show',
                'icon' => 'show.svg',
            ]
        ]
    ],
    'palettes' => [
        '__selector__' => ['type', 'singleDocument'],
        'default' => 'signature;type',
        'licence' => 'signature;type;proxyUrl,proxyApiKey;authToken,keys;singleDocument'
    ],
    'subpalettes' => [
        'singleDocument' => 'analyzer'
    ],
    'fields' => [
        'id' => [
            'sql' => ['type' => 'integer', 'autoincrement' => true, 'notnull' => true, 'unsigned' => true]
        ],
        'tstamp' => [
            'flag' => 6,
            'sql' => ['type' => 'integer', 'notnull' => false, 'unsigned' => true, 'default' => 0]
        ],
        'signature' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 32,
                'tl_class' => 'w50',
                'mandatory' => true,
                'readonly' => true
            ],
            'sql' => "varchar(32) NOT NULL default ''"
        ],
        'type' => [
            'inputType' => 'select',
            'eval' => [
                'maxlength' => 32,
                'tl_class' => 'w50',
                'submitOnChange' => true,
                'includeBlankOption' => true
            ],
            'options' => ['licence'],
            'reference' => &$GLOBALS['TL_LANG']['tl_search_credentials'],
            'sql' => "varchar(32) NOT NULL default ''"
        ],
        'host' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 64,
                'tl_class' => 'w50',
                'mandatory' => true
            ],
            'sql' => "varchar(64) NOT NULL default ''"
        ],
        'port' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 16,
                'tl_class' => 'w50',
                'mandatory' => false
            ],
            'sql' => "varchar(16) NOT NULL default ''"
        ],
        'username' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 128,
                'tl_class' => 'w50',
                'mandatory' => false
            ],
            'sql' => "varchar(128) NOT NULL default ''"
        ],
        'proxyUrl' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 255,
                'tl_class' => 'w50 clr',
                'decodeEntities' => true,
                'placeholder' => System::getContainer()->hasParameter('prosearch_adapter.proxy.url') ? (string)System::getContainer()->getParameter('prosearch_adapter.proxy.url') : ''
            ],
            'save_callback' => [function ($value) {
                $value = trim((string)$value);

                if ('' === $value) {
                    return ''; // configured default (PROSEARCH_PROXY_URL)
                }

                try {
                    $value = ProxyClient::normalizeBaseUrl($value);
                } catch (\InvalidArgumentException $exception) {
                    throw new Exception($exception->getMessage());
                }

                $host = strtolower((string)parse_url($value, PHP_URL_HOST));

                if ('http' === strtolower((string)parse_url($value, PHP_URL_SCHEME)) && !in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
                    Message::addInfo('Die ProSearch-Proxy-URL nutzt http: Lizenz und API-Token werden unverschlüsselt übertragen. Im Live-Betrieb https verwenden.');
                }

                return $value;
            }],
            'sql' => "varchar(255) NOT NULL default ''"
        ],
        'proxyApiKey' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 255,
                'tl_class' => 'w50',
                'decodeEntities' => true,
                'hideInput' => true,
                'autocomplete' => 'off'
            ],
            'sql' => "varchar(255) NOT NULL default ''"
        ],
        'singleDocument' => [
            'inputType' => 'checkbox',
            'eval' => [
                'tl_class' => 'clr',
                'submitOnChange' => true
            ],
            'sql' => "char(1) NOT NULL default ''"
        ],
        'password' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 128,
                'tl_class' => 'w50',
                'mandatory' => false,
                'decodeEntities' => true
            ],
            'sql' => "varchar(128) NOT NULL default ''"
        ],
        'key' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 255,
                'mandatory' => true,
                'tl_class' => 'long clr',
                'decodeEntities' => true
            ],
            'sql' => "varchar(255) NOT NULL default ''"
        ],
        'authToken' => [
            'inputType' => 'text',
            'eval' => [
                'mandatory' => true,
                'tl_class' => 'long clr',
                'decodeEntities' => true
            ],
            'sql' => "text NULL"
        ],
        'keys' => [
            'inputType' => 'multiColumnWizard',
            'eval' => [
                'mandatory' => true,
                'tl_class' => 'long clr',
                'decodeEntities' => true,
                'columnFields' => [
                    'key' => [
                        'label' => &$GLOBALS['TL_LANG']['tl_search_credentials']['key'],
                        'inputType' => 'text',
                        'eval' => ['style' => 'width:100%;min-width:200px']
                    ],
                    'domain' => [
                        'label' => &$GLOBALS['TL_LANG']['tl_search_credentials']['domain'],
                        'inputType' => 'text',
                        'eval' => ['style' => 'width:100%;'],
                        'save_callback' => [function ($val) {
                            return System::getContainer()->get(LicenseProvider::class)->parseDomain((string)$val);
                        }]
                    ]
                ]
            ],
            'save_callback' => [function ($values, DataContainer $dc) {
                $container = System::getContainer();
                $licenseProvider = $container->get(LicenseProvider::class);
                $keys = StringUtil::deserialize($values, true);
                $proxy = $container->get(ProxyClient::class);
                $proxyUrl = trim((string)($dc->activeRecord->proxyUrl ?? ''));

                if ('' !== $proxyUrl) {
                    try {
                        $proxy = $proxy->withConnection($proxyUrl, trim((string)($dc->activeRecord->proxyApiKey ?? '')));
                    } catch (\InvalidArgumentException $exception) {
                        throw new Exception($exception->getMessage());
                    }
                }

                foreach ($keys as $key) {
                    $domain = $licenseProvider->parseDomain($key['domain'] ?? '');
                    $license = $licenseProvider->encodeLicense($key['key'] ?? '', $domain, (string)$dc->activeRecord->authToken);

                    try {
                        $status = $proxy->getLicenseStatus($license);
                    } catch (ProxyException $exception) {
                        throw new Exception('The licence for domain ' . $domain . ' could not be validated, the ProSearch proxy ' . $proxy->getBaseUrl() . ' is not reachable: ' . $exception->getMessage());
                    }

                    if (!($status['valid'] ?? false)) {
                        throw new Exception('Licence for domain ' . $domain . ' is invalid! ' . ($status['message'] ?? 'You can purchase a licence at this address: https://app.sineos.de'));
                    }
                }

                return $values;
            }],
            'sql' => "blob NULL"
        ],
        'cert' => [
            'inputType' => 'text',
            'eval' => [
                'maxlength' => 255,
                'tl_class' => 'w50'
            ],
            'sql' => "varchar(255) NOT NULL default ''"
        ],
        'analyzer' => [
            'inputType' => 'select',
            'default' => 'contao',
            'eval' => [
                'chosen' => true,
                'tl_class' => 'w50',
                'mandatory' => true,
                'includeBlankOption' => true
            ],
            'options_callback' => function () {
                $arrAnalyzer = \array_keys(System::getContainer()->get(MappingFactory::class)->getAnalyzers());

                return \array_values(\array_unique([...$arrAnalyzer, 'standard', 'simple', 'whitespace', 'stop']));
            },
            'reference' => &$GLOBALS['TL_LANG']['MSC']['psAnalyzer'],
            'sql' => "varchar(64) NOT NULL default 'contao'"
        ],
        'engineType' => [
            'sql' => "varchar(32) NOT NULL default ''"
        ],
        'engineVersion' => [
            'sql' => "varchar(32) NOT NULL default ''"
        ],
        'engineChunks' => [
            'sql' => "char(1) NOT NULL default ''"
        ],
        'engineVectors' => [
            'sql' => "char(1) NOT NULL default ''"
        ],
        'engineCheckedAt' => [
            'sql' => ['type' => 'integer', 'notnull' => true, 'unsigned' => true, 'default' => 0]
        ],
        'engineError' => [
            'sql' => "varchar(255) NOT NULL default ''"
        ],
        'engineConnection' => [
            'sql' => "varchar(64) NOT NULL default ''"
        ]
    ]
];