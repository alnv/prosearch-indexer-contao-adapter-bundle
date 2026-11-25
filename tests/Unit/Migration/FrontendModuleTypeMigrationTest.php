<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Unit\Migration;

use Alnv\ProSearchIndexerContaoAdapterBundle\Migration\FrontendModuleTypeMigration;
use PHPUnit\Framework\TestCase;

final class FrontendModuleTypeMigrationTest extends TestCase
{
    public function testRenamesTypesAndKeepsOthers(): void
    {
        $this->assertSame(
            ['navigation', 'prosearch', 'search', 'prosearch_type_ahead'],
            FrontendModuleTypeMigration::renameTypes(['navigation', 'elasticsearch', 'search', 'elasticsearch_type_ahead']),
        );
    }

    public function testRemovesDuplicates(): void
    {
        $this->assertSame(['prosearch', 'login'], FrontendModuleTypeMigration::renameTypes(['prosearch', 'login', 'elasticsearch']));
    }
}
