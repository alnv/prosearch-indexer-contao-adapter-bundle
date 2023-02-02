<?php

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Search;

class Credentials
{

    public function getCredentials() {

        $objCredentials = \Database::getInstance()->prepare('SELECT * FROM tl_search_credentials ORDER BY id DESC')->limit(1)->execute();

        if (!$objCredentials->numRows) {
            return false;
        }

        return $objCredentials->row();
    }
}