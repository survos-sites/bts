<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use DuckDb\DBAL\Platforms\DuckDBPlatform;

/** ORM schema compatibility inherited from the Symfony demo experiment. */
final class BtsDuckDbPlatform extends DuckDBPlatform
{
    public function getJsonTypeDeclarationSQL(array $column): string
    {
        // Doctrine JsonType decodes text. Native DuckDB JSON is already decoded by PDO.
        return 'VARCHAR';
    }

    public function getCreateTablesSQL(array $tables): array
    {
        $pending = [];
        foreach ($tables as $table) {
            $pending[$table->getName()] = $table;
        }
        $sql = [];
        while ([] !== $pending) {
            $progress = false;
            foreach ($pending as $name => $table) {
                foreach ($table->getForeignKeys() as $foreignKey) {
                    $parent = $foreignKey->getForeignTableName();
                    if ($parent !== $name && isset($pending[$parent])) {
                        continue 2;
                    }
                }
                $sql = [...$sql, ...$this->getCreateTableSQL($table)];
                unset($pending[$name]);
                $progress = true;
            }
            if (!$progress) {
                throw new \LogicException('DuckDB cannot create this cyclic foreign-key graph inline.');
            }
        }

        return $sql;
    }

    public function getAdvancedForeignKeyOptionsSQL(ForeignKeyConstraint $foreignKey): string
    {
        // DuckDB has no SQL ON DELETE CASCADE. The demo's ORM owns join-table cleanup.
        // Keep the FK itself; raw SQL deletes remain restricted. This difference is tested/documented.
        foreach (['onDelete', 'onUpdate'] as $option) {
            if ($foreignKey->hasOption($option) && !in_array(strtoupper($foreignKey->getOption($option)), ['NO ACTION', 'RESTRICT', 'CASCADE'], true)) {
                throw new \LogicException('Unsupported DuckDB referential action: '.$option);
            }
        }

        return '';
    }

    public function getDropTablesSQL(array $tables): array
    {
        // Remove children before parents; DuckDB cannot ALTER TABLE DROP FOREIGN KEY.
        $pending = [];
        foreach ($tables as $table) {
            $pending[$table->getName()] = $table;
        }
        $sql = [];
        while ([] !== $pending) {
            $progress = false;
            foreach ($pending as $name => $table) {
                foreach ($pending as $otherName => $other) {
                    if ($otherName === $name) {
                        continue;
                    }
                    foreach ($other->getForeignKeys() as $foreignKey) {
                        if ($foreignKey->getForeignTableName() === $name) {
                            continue 3;
                        }
                    }
                }
                $sql[] = $this->getDropTableSQL($table->getQuotedName($this));
                unset($pending[$name]);
                $progress = true;
            }
            if (!$progress) {
                throw new \LogicException('DuckDB cannot drop this cyclic foreign-key graph.');
            }
        }
        return $sql;
    }
}
