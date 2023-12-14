<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Helpers;

use Contao\Database;

class Credentials
{

    public function getCredentials(): array|bool
    {

        $objCredentials = Database::getInstance()->prepare('SELECT * FROM tl_search_credentials ORDER BY id DESC')->limit(1)->execute();

        if (!$objCredentials->numRows) {
            return false;
        }

        return $objCredentials->row();
    }
}