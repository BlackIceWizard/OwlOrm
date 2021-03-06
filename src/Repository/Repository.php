<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm\Repository;

use InvalidArgumentException;
use Iterator;
use JetBrains\PhpStorm\Pure;
use RiverRing\OwlOrm\Aggregator;
use RiverRing\OwlOrm\Dbal\Driver\Driver;
use RiverRing\OwlOrm\Dumper;
use RiverRing\OwlOrm\Mapping\MapperRegistry;
use RiverRing\OwlOrm\Repository\DbRepresentation\RawData;
use RiverRing\OwlOrm\Repository\DbRepresentation\Record;
use RiverRing\OwlOrm\Specification\AggregateRootSpecification;
use RiverRing\OwlOrm\Specification\EntitySpecification;
use RiverRing\OwlOrm\Specification\PluralEntitySpecification;
use RiverRing\OwlOrm\Specification\SingleEntitySpecification;

/**
 * @template T
 */
abstract class Repository
{
    private Driver $driver;
    private Aggregator $aggregator;
    private Dumper $dumper;

    #[Pure]
    public function __construct(Driver $driver, MapperRegistry $mappers)
    {
        $this->driver = $driver;
        $this->aggregator = new Aggregator($mappers);
        $this->dumper = new Dumper($mappers);
    }

    abstract protected function specification(): AggregateRootSpecification;

    protected function findOne($sql, $params = []): ?array
    {
        return $this->driver->findOne($sql, $params);
    }

    protected function find($sql, $params = []): Iterator
    {
        return $this->driver->find($sql, $params);
    }

    /**
     * @param EntitySpecification[] $entitySpecifications
     */
    private function findEntities(array $entitySpecifications, int|string $aggregateRootId): array
    {
        $entities = [];
        foreach ($entitySpecifications as $specification) {
            $entities[$specification->className()] = match (true) {
                $specification instanceof SingleEntitySpecification => $this->driver->findEntity(
                    $aggregateRootId,
                    $specification->table(),
                    $specification->referencedField()
                ),
                $specification instanceof PluralEntitySpecification => $this->driver->findEntitySet(
                    $aggregateRootId,
                    $specification->table(),
                    $specification->referencedField()
                ),
                default => throw new InvalidArgumentException(sprintf('Unexpected entity specification class %s', get_class($specification))),
            };
        }

        return $entities;
    }

    /**
     * @return T|null
     */
    protected function aggregateOne(?array $aggregateRootData): ?object
    {
        if ($aggregateRootData === null) {
            return null;
        }

        $aggregateRootSpecification = $this->specification();

        $entitiesData = $this->findEntities(
            $aggregateRootSpecification->entitySpecifications(),
            $aggregateRootData[$aggregateRootSpecification->primaryKeyField()]
        );

        $allData = [$aggregateRootSpecification->className() => $aggregateRootData] + $entitiesData;

        return $this->aggregator->aggregate(
            $aggregateRootSpecification,
            new RawData(
                array_combine(
                    array_keys($allData),
                    array_map(
                        static function ($recordData) {
                            if ($recordData === null) {
                                return null;
                            }

                            if ($recordData instanceof Iterator) {
                                return array_map(
                                    fn($data): Record => Record::justLoaded($data),
                                    iterator_to_array($recordData)
                                );
                            }

                            return Record::justLoaded($recordData);
                        },
                        $allData
                    )
                )
            )
        );
    }

    public function store(object $aggregateRoot): void
    {
        $aggregateRootSpecification = $this->specification();
        $rawData = $this->dumper->dump($aggregateRootSpecification, $aggregateRoot);

        $this->driver->transactional(function () use ($rawData, $aggregateRootSpecification) {
            $this->driver->store($aggregateRootSpecification->table(), $aggregateRootSpecification->primaryKeyField(), $rawData->byClassname($aggregateRootSpecification->className()));

            foreach ($aggregateRootSpecification->entitySpecifications() as $entitySpecification) {
                $entityClassName = $entitySpecification->className();
                switch (true) {
                    case $entitySpecification instanceof SingleEntitySpecification:
                        $this->driver->store($entitySpecification->table(), $entitySpecification->primaryKeyField(), $rawData->byClassname($entityClassName));
                        break;
                    case $entitySpecification instanceof PluralEntitySpecification:
                        foreach ($rawData->byClassname($entityClassName) as $entityRecord) {
                            $this->driver->store($entitySpecification->table(), $entitySpecification->primaryKeyField(), $entityRecord);
                        }
                        break;
                    default:
                        throw new InvalidArgumentException(sprintf('Unexpected entity specification class %s', get_class($entitySpecification)));
                }
            }
        });
    }
}