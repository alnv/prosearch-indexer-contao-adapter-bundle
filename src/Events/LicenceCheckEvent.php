<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Events;


class LicenceCheckEvent
{

    public function isValidLicence($strLicense, $blnCache = true): bool
    {
        return in_array($strLicense, ['ck-23-kiel', 'alpha-test']);
    }
}