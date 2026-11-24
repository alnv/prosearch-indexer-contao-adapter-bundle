<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

use Alnv\ProSearchIndexerContaoAdapterBundle\Models\IndicesModel;
use Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\LicenseProvider;
use Contao\PageModel;

class IndexNameResolver
{
    public const string INDEX_PREFIX = 'contao_search';

    public function __construct(private readonly LicenseProvider $licenseProvider)
    {
    }

    public function getIndexName(int|string|null $rootIdentifier): string
    {
        $rootIdentifier = (string)$rootIdentifier;

        if ($this->licenseProvider->isSingleDocument()) {
            $rootIdentifier = 'single';
        }

        return self::INDEX_PREFIX . '_' . $this->licenseProvider->getSignature() . ($rootIdentifier && '0' !== $rootIdentifier ? '_' . $rootIdentifier : '');
    }

    public function getRootIdentifierFromIndicesId(int|string $indicesId): string
    {
        $indices = IndicesModel::findByPk($indicesId);

        if (!$indices || !$indices->pageId) {
            return '';
        }

        $page = PageModel::findByPk($indices->pageId);

        if (!$page) {
            return '';
        }

        $page->loadDetails();

        return (string)$page->rootId;
    }
}
