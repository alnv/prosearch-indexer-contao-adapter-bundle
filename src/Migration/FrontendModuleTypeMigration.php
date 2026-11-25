<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * Renames the front end module types "elasticsearch" and "elasticsearch_type_ahead" to "prosearch" and
 * "prosearch_type_ahead".
 *
 * Updates the modules (tl_module.type) and the allowed module types of back end users and groups
 * (frontendModules, serialized arrays). Templates (customTpl, psResultsTemplate) keep their names and need no
 * change. Reruns are no-ops.
 */
class FrontendModuleTypeMigration extends AbstractMigration
{
    public const array TYPES = [
        'elasticsearch' => 'prosearch',
        'elasticsearch_type_ahead' => 'prosearch_type_ahead',
    ];

    private const array PERMISSION_TABLES = ['tl_user', 'tl_user_group'];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'ProSearch 4.0: front end module types elasticsearch* → prosearch*';
    }

    public function shouldRun(): bool
    {
        if ($this->hasColumn('tl_module', 'type') && $this->connection->fetchOne('SELECT 1 FROM tl_module WHERE type IN (?, ?) LIMIT 1', array_keys(self::TYPES))) {
            return true;
        }

        foreach (self::PERMISSION_TABLES as $table) {
            if ([] !== $this->findPermissionRows($table)) {
                return true;
            }
        }

        return false;
    }

    public function run(): MigrationResult
    {
        $counts = $this->connection->transactional(function (Connection $connection): array {
            $modules = 0;

            if ($this->hasColumn('tl_module', 'type')) {
                foreach (self::TYPES as $old => $new) {
                    $modules += (int) $connection->executeStatement('UPDATE tl_module SET type=? WHERE type=?', [$new, $old]);
                }
            }

            $permissions = 0;

            foreach (self::PERMISSION_TABLES as $table) {
                foreach ($this->findPermissionRows($table) as $id => $types) {
                    $connection->update($table, ['frontendModules' => serialize(self::renameTypes($types))], ['id' => $id]);
                    ++$permissions;
                }
            }

            return [$modules, $permissions];
        });

        return $this->createResult(true, \sprintf('Renamed %d front end module(s) and updated the module permissions of %d user(s)/group(s).', ...$counts));
    }

    /**
     * Keeps the order, removes duplicates (old and new type both selected).
     *
     * @param list<mixed> $types
     *
     * @return list<mixed>
     */
    public static function renameTypes(array $types): array
    {
        $renamed = [];

        foreach ($types as $type) {
            $type = \is_string($type) ? (self::TYPES[$type] ?? $type) : $type;

            if (!\in_array($type, $renamed, true)) {
                $renamed[] = $type;
            }
        }

        return $renamed;
    }

    /**
     * @return array<int, list<mixed>> id => allowed module types, only rows containing an old type
     */
    private function findPermissionRows(string $table): array
    {
        if (!$this->hasColumn($table, 'frontendModules')) {
            return [];
        }

        $rows = [];
        $result = $this->connection->executeQuery(
            "SELECT id, frontendModules FROM $table WHERE frontendModules LIKE ? OR frontendModules LIKE ?",
            ['%"elasticsearch"%', '%"elasticsearch_type_ahead"%'],
        );

        foreach ($result->iterateAssociative() as $row) {
            $types = @unserialize((string) $row['frontendModules'], ['allowed_classes' => false]);

            if (\is_array($types) && array_intersect(array_filter($types, 'is_string'), array_keys(self::TYPES))) {
                $rows[(int) $row['id']] = array_values($types);
            }
        }

        return $rows;
    }

    private function hasColumn(string $table, string $column): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([$table])) {
            return false;
        }

        return isset(array_change_key_case($schemaManager->listTableColumns($table))[strtolower($column)]);
    }
}
