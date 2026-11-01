<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

#[AsDoctrineListener(event: ToolEvents::postGenerateSchema)]
final class DuckDbSchemaListener
{
    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        if (!$event->getEntityManager()->getConnection()->getDatabasePlatform() instanceof BtsDuckDbPlatform) {
            return;
        }
        $platform = $event->getEntityManager()->getConnection()->getDatabasePlatform();
        foreach ($event->getEntityManager()->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isMappedSuperclass || !$metadata->isIdGeneratorSequence()) {
                continue;
            }
            $column = $event->getSchema()->getTable($metadata->getTableName())->getColumn($metadata->getSingleIdentifierColumnName());
            // Babel's DBAL upserts omit id; ORM sequence allocation alone supplies no server default.
            $type = $column->getType()->getSQLDeclaration($column->toArray(), $platform);
            $column->setColumnDefinition($type.' DEFAULT nextval('.$platform->quoteStringLiteral($metadata->sequenceGeneratorDefinition['sequenceName']).') NOT NULL');
        }
        foreach ($event->getSchema()->getTables() as $table) {
            foreach ($table->getForeignKeys() as $foreignKey) {
                $options = $foreignKey->getOptions();
                if (!isset($options['onDelete']) && !isset($options['onUpdate'])) {
                    continue;
                }
                unset($options['onDelete'], $options['onUpdate']);
                $name = $foreignKey->getName();
                $table->dropForeignKey($name);
                $table->addForeignKeyConstraint($foreignKey->getForeignTableName(), $foreignKey->getLocalColumns(), $foreignKey->getForeignColumns(), $options, $name);
            }
        }
    }
}
