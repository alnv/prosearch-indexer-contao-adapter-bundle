<?php

declare(strict_types=1);

/*
 * Uses the bundle's own vendor directory (composer install in the bundle) or, when developed as
 * path repository inside a project (_packages/<bundle>), the project's autoloader.
 */
$candidates = [__DIR__.'/../vendor/autoload.php', __DIR__.'/../../../vendor/autoload.php'];

foreach ($candidates as $file) {
    if (is_file($file)) {
        $loader = require $file;
        $loader->addPsr4('Alnv\\ProSearchIndexerContaoAdapterBundle\\Tests\\', __DIR__);
        $loader->addPsr4('Alnv\\ProSearchIndexerContaoAdapterBundle\\', __DIR__.'/../src', true);

        return;
    }
}

throw new RuntimeException('No autoloader found, run "composer install" in the bundle directory.');
