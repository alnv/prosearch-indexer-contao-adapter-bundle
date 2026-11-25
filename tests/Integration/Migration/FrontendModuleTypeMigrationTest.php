<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Integration\Migration;

use Alnv\ProSearchIndexerContaoAdapterBundle\Migration\FrontendModuleTypeMigration;
use Alnv\ProSearchIndexerContaoAdapterBundle\Tests\Integration\DatabaseTestCase;

final class FrontendModuleTypeMigrationTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // relevant columns as in Contao 5 (tl_module.type, frontendModules blob)
        $this->connection->executeStatement("CREATE TABLE tl_module (id INT UNSIGNED AUTO_INCREMENT NOT NULL, type VARCHAR(64) DEFAULT 'navigation' NOT NULL, customTpl VARCHAR(64) DEFAULT '' NOT NULL, PRIMARY KEY (id))");
        $this->connection->executeStatement('CREATE TABLE tl_user (id INT UNSIGNED AUTO_INCREMENT NOT NULL, frontendModules BLOB DEFAULT NULL, PRIMARY KEY (id))');
        $this->connection->executeStatement('CREATE TABLE tl_user_group (id INT UNSIGNED AUTO_INCREMENT NOT NULL, frontendModules BLOB DEFAULT NULL, PRIMARY KEY (id))');
    }

    public function testRenamesModulesAndPermissions(): void
    {
        $this->connection->insert('tl_module', ['type' => 'elasticsearch', 'customTpl' => 'mod_elasticsearch_custom']);
        $this->connection->insert('tl_module', ['type' => 'elasticsearch_type_ahead']);
        $this->connection->insert('tl_module', ['type' => 'search']);
        $this->connection->insert('tl_user', ['frontendModules' => serialize(['search', 'elasticsearch'])]);
        $this->connection->insert('tl_user', ['frontendModules' => serialize(['navigation'])]);
        $this->connection->insert('tl_user', ['frontendModules' => null]);
        $this->connection->insert('tl_user_group', ['frontendModules' => serialize(['elasticsearch_type_ahead', 'prosearch_type_ahead'])]);

        $migration = new FrontendModuleTypeMigration($this->connection);
        $this->assertTrue($migration->shouldRun());

        $result = $migration->run();
        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('Renamed 2 front end module(s)', $result->getMessage());
        $this->assertStringContainsString('of 2 user(s)/group(s)', $result->getMessage());

        $this->assertSame(['prosearch', 'prosearch_type_ahead', 'search'], $this->connection->fetchFirstColumn('SELECT type FROM tl_module ORDER BY id'));
        $this->assertSame('mod_elasticsearch_custom', $this->connection->fetchOne('SELECT customTpl FROM tl_module WHERE id=1'), 'templates keep their names');
        $this->assertSame(['search', 'prosearch'], unserialize($this->connection->fetchOne('SELECT frontendModules FROM tl_user WHERE id=1')));
        $this->assertSame(['navigation'], unserialize($this->connection->fetchOne('SELECT frontendModules FROM tl_user WHERE id=2')));
        $this->assertNull($this->connection->fetchOne('SELECT frontendModules FROM tl_user WHERE id=3'));
        $this->assertSame(['prosearch_type_ahead'], unserialize($this->connection->fetchOne('SELECT frontendModules FROM tl_user_group WHERE id=1')));

        $this->assertFalse($migration->shouldRun(), 'reruns are no-ops');
    }

    public function testSkipsWithoutOldTypes(): void
    {
        $this->connection->insert('tl_module', ['type' => 'prosearch']);
        // only a substring of an old type name
        $this->connection->insert('tl_user', ['frontendModules' => serialize(['elasticsearch_custom'])]);

        $this->assertFalse((new FrontendModuleTypeMigration($this->connection))->shouldRun());
    }
}
