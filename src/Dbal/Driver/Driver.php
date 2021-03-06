<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm\Dbal\Driver;

use Iterator;
use RiverRing\OwlOrm\Repository\DbRepresentation\Record;

interface Driver extends TransactionalRunner
{
    public function execute($sql, $params = []): void;

    public function findOne($sql, $params = []): ?array;

    public function find($sql, $params = []): Iterator;

    public function findEntitySet(string|int $aggregateRootId, string $entityTable, string $referencedFieldName): Iterator;

    public function findEntity(string|int $aggregateRootId, string $entityTable, string $referencedFieldName): ?array;

    public function store(string $table, string $primaryKeyField, Record $record): void;
}