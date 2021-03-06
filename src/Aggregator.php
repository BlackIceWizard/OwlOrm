<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm;

use InvalidArgumentException;
use RiverRing\OwlOrm\Mapping\Extract;
use RiverRing\OwlOrm\Mapping\MapperRegistry;
use RiverRing\OwlOrm\Mapping\PrimaryMapper;
use RiverRing\OwlOrm\Repository\DbRepresentation\RawData;
use RiverRing\OwlOrm\Repository\DbRepresentation\Record;
use RiverRing\OwlOrm\Specification\AggregateRootSpecification;
use RiverRing\OwlOrm\Specification\EmbeddableSpecification;
use RiverRing\OwlOrm\Specification\EntitySpecification;
use RiverRing\OwlOrm\Specification\PluralEntitySpecification;
use RiverRing\OwlOrm\Specification\SingleEntitySpecification;

/**
 * @template T
 */
final class Aggregator
{
    private MapperRegistry $mappers;

    public function __construct(MapperRegistry $mappers)
    {
        $this->mappers = $mappers;
    }

    public function aggregate(AggregateRootSpecification $specification, RawData $rawData): object
    {
        $aggregateRootMapper = $this->mappers->aggregateRootMapper($specification->className());
        $aggregateRootRecord = $rawData->byClassname($specification->className());
        $aggregateRootData = $aggregateRootRecord->data();

        return $aggregateRootMapper->hydrate(
            Extract::ofAggregateRoot(
                $this->excludeEmbeddableFields($aggregateRootData, $aggregateRootMapper->embeddable()),
                $this->hydrateAllEntities($specification->entitySpecifications(), $rawData),
                $this->hydrateEmbeddable(
                    $this->excludeNotEmbeddableFields($aggregateRootMapper->embeddable(), $aggregateRootData),
                    $aggregateRootMapper->embeddable()
                )
            ),
            $aggregateRootRecord->hash()
        );
    }

    /**
     * @param EntitySpecification[] $specifications
     * @return object[]
     */
    private function hydrateAllEntities(array $specifications, RawData $rawData): array
    {
        $hydrated = [];
        foreach ($specifications as $specification) {
            $className = $specification->className();
            $mapper = $this->mappers->entityMapper($className);

            $hydrated[$className] = match (true) {
                $specification instanceof SingleEntitySpecification => $this->hydrateSingleEntity($mapper, $rawData->byClassname($className), $specification),
                $specification instanceof PluralEntitySpecification => $this->hydrateMultipleEntities($mapper, $rawData->byClassname($className), $specification),
                default => throw new InvalidArgumentException(sprintf('Unexpected entity specification class %s', get_class($specification))),
            };
        }

        return $hydrated;
    }

    private function hydrateSingleEntity(PrimaryMapper $mapper, ?Record $record, SingleEntitySpecification $specification): ?object
    {
        if ($record === null) {
            return null;
        }

        return $this->hydrateEntity($record, $mapper, $specification);
    }

    /**
     * @param Record[] $records
     * @return object[]
     */
    private function hydrateMultipleEntities(PrimaryMapper $mapper, array $records, PluralEntitySpecification $specification): array
    {
        return array_map(
            fn(Record $record): object => $this->hydrateEntity($record, $mapper, $specification),
            $records
        );
    }

    private function hydrateEntity(Record $record, PrimaryMapper $mapper, EntitySpecification $specification): object
    {
        $recordData = $record->data();

        return $mapper->hydrate(
            Extract::ofEntity(
                $this->excludeFields(
                    $this->excludeEmbeddableFields($recordData, $mapper->embeddable()),
                    $specification->referencedField()
                ),
                $this->hydrateEmbeddable($recordData, $mapper->embeddable())
            ),
            $record->hash()
        );
    }

    /**
     * @param EmbeddableSpecification[] $specifications
     */
    private function hydrateEmbeddable(array $data, array $specifications): array
    {
        $embeddable = [];

        foreach ($specifications as $key => $specification) {
            $mapper = $this->mappers->embeddedMapper($specification->className());
            $embeddable[$key] = $mapper->hydrate(
                Extract::ofEmbeddable(
                    $this->removeDataKeyPrefix(
                        $this->excludeFieldsWithoutPrefix($data, $specification->prefix()),
                        $specification->prefix()
                    )
                )
            );
        }

        return $embeddable;
    }

    private function excludeFields(array $data, string ...$names): array
    {
        return array_filter(
            $data,
            fn(string $key): bool => ! in_array($key, $names),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @param EmbeddableSpecification[] $specifications
     */
    private function excludeNotEmbeddableFields(array $specifications, array $data): array
    {
        return $this->excludeFieldsWithoutPrefix(
            $data,
            ...array_map(
                fn(EmbeddableSpecification $specification) => $specification->prefix(),
                $specifications
            )
        );
    }

    /**
     * @param EmbeddableSpecification[] $specifications
     */
    private function excludeEmbeddableFields(array $data, array $specifications): array
    {
        return $this->excludeFieldsByPrefix(
            $data,
            ...array_map(
                fn(EmbeddableSpecification $specification) => $specification->prefix(),
                $specifications
            )
        );
    }

    private function removeDataKeyPrefix(array $data, string $prefix): array
    {
        return array_combine(
            array_map(
                fn(string $key): string => substr($key, strlen($prefix)),
                array_keys($data)
            ),
            array_values($data)
        );
    }

    private function excludeFieldsWithoutPrefix(array $data, ...$prefixes): array
    {
        return $this->filterData($data, $prefixes, true);
    }

    private function excludeFieldsByPrefix(array $data, ...$prefixes): array
    {
        return $this->filterData($data, $prefixes, false);
    }

    private function filterData(array $data, array $keyPrefixes, bool $contains): array
    {
        foreach ($keyPrefixes as $keyPrefix) {
            $data = array_filter(
                $data,
                fn(string $key): bool => str_starts_with($key, $keyPrefix) ? $contains : ! $contains,
                ARRAY_FILTER_USE_KEY
            );
        }

        return $data;
    }
}