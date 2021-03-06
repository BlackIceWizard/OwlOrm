<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm;

use InvalidArgumentException;
use RiverRing\OwlOrm\Mapping\Augmentation;
use RiverRing\OwlOrm\Mapping\MapperRegistry;
use RiverRing\OwlOrm\Mapping\PrimaryMapper;
use RiverRing\OwlOrm\Repository\DbRepresentation\RawData;
use RiverRing\OwlOrm\Repository\DbRepresentation\Record;
use RiverRing\OwlOrm\Specification\AggregateRootSpecification;
use RiverRing\OwlOrm\Specification\EmbeddableSpecification;
use RiverRing\OwlOrm\Specification\EntitySpecification;
use RiverRing\OwlOrm\Specification\PluralEntitySpecification;
use RiverRing\OwlOrm\Specification\SingleEntitySpecification;
use RuntimeException;

final class Dumper
{
    private MapperRegistry $mappers;

    public function __construct(MapperRegistry $mappers)
    {
        $this->mappers = $mappers;
    }

    public function dump(AggregateRootSpecification $specification, object $aggregateRoot): RawData
    {
        $aggregateRootMapper = $this->mappers->aggregateRootMapper($specification->className());
        $aggregateRootExtract = $aggregateRootMapper->dehydrate($aggregateRoot);

        $mapperClassName = get_class($aggregateRootMapper);

        $embeddableList = $aggregateRootExtract->embeddable();
        $embeddableSpecifications = $aggregateRootMapper->embeddable();

        $this->checkEmbeddableList($mapperClassName, $embeddableList, $embeddableSpecifications);

        $aggregateRootRecordData = $aggregateRootExtract->data()
            + $this->dehydrateEmbeddableData($embeddableList, $embeddableSpecifications);

        $primaryKeyValue =  $aggregateRootRecordData[$specification->primaryKeyField()];

        $aggregateRootRecord = $aggregateRoot instanceof Augmentation
            ? Record::previouslyLoaded($aggregateRootRecordData, $aggregateRoot->stateHash())
            : Record::new($aggregateRootRecordData);

        $entities = $aggregateRootExtract->entities();
        $entitySpecifications = $specification->entitySpecifications();

        $this->checkEntities($mapperClassName, $entities, $entitySpecifications);

        return new RawData(
            [$specification->className() => $aggregateRootRecord]
            + $this->dehydrateAllEntities($entities, $entitySpecifications, $primaryKeyValue)
        );
    }

    /**
     * @param class-string $mapperClassName
     * @param object[] $entities
     * @param EntitySpecification[] $specifications
     */
    private function checkEntities(string $mapperClassName, array $entities, array $specifications): void
    {
        $entitiesCount = count($entities);
        $specificationCount = count($specifications);

        if ($specificationCount !== $entitiesCount) {
            throw new RuntimeException(
                sprintf(
                    'Entities count (%d) no equals to entities specification count (%d) in extract of mapper %s',
                    $entitiesCount,
                    $specificationCount,
                    $mapperClassName
                )
            );
        }

        foreach ($specifications as $specification) {
            $entityClassName = $specification->className();
            
            if (! isset($entities[$entityClassName])) {
                throw new RuntimeException(
                    sprintf(
                        'Entity %s not found in extract of mapper %s',
                        $specification->className(),
                        $mapperClassName
                    )
                );
            }

            switch (true) {
                case $specification instanceof SingleEntitySpecification:
                    if (is_array($entities[$entityClassName])) {
                        throw new InvalidArgumentException(sprintf('Entity %s specified as single but extract of mapper %s contains array of entity instances', $entityClassName, $mapperClassName));
                    }
                    break;
                case $specification instanceof PluralEntitySpecification:
                    if (! is_array($entities[$entityClassName])) {
                        throw new InvalidArgumentException(sprintf('Entity %s specified as plural but extract of mapper %s contains single entity instance', $entityClassName, $mapperClassName));
                    }
                    break;
                default:
                    throw new InvalidArgumentException(sprintf('Unexpected entity specification class %s', get_class($specification)));
            }
        }
    }

    /**
     * @param EntitySpecification[] $specifications
     * @return array
     */
    private function dehydrateAllEntities(array $entities, array $specifications, int|string $referencedFieldValue): array
    {
        $records = [];
        foreach ($specifications as $specification) {
            $mapper = $this->mappers->entityMapper($specification->className());
            $entityClassName = $specification->className();
            $referencedField = [$specification->referencedField() => $referencedFieldValue];

            $records[$entityClassName] = match (true) {
                $specification instanceof SingleEntitySpecification => $this->dehydrateSingleEntity(
                    $mapper,
                    $entities[$entityClassName],
                    $referencedField
                ),
                $specification instanceof PluralEntitySpecification => $this->dehydrateMultipleEntities(
                    $mapper,
                    $entities[$entityClassName],
                    $referencedField
                ),
                default => throw new InvalidArgumentException(sprintf('Unexpected entity specification class %s', get_class($specification))),
            };
        }

        return $records;
    }

    private function dehydrateSingleEntity(PrimaryMapper $mapper, ?object $entity, array $referencedField): ?Record
    {
        if ($entity === null) {
            return null;
        }

        return $this->dehydrateEntity($mapper, $entity, $referencedField);
    }

    /**
     * @param object[] $entities
     * @return Record[]
     */
    private function dehydrateMultipleEntities(PrimaryMapper $mapper, array $entities, array $referencedField): array
    {
        return array_map(
            fn(object $entity): object => $this->dehydrateEntity($mapper, $entity, $referencedField),
            $entities
        );
    }

    /**
     * @param PrimaryMapper $mapper
     * @param object $entity
     * @return Record
     */
    private function dehydrateEntity(PrimaryMapper $mapper, object $entity, array $referencedField): Record
    {
        $extract = $mapper->dehydrate($entity);

        $mapperClassName = get_class($mapper);

        $embeddableList = $extract->embeddable();
        $embeddableSpecifications = $mapper->embeddable();

        $this->checkEmbeddableList($mapperClassName, $embeddableList, $embeddableSpecifications);

        $aggregateRootRecordData = $extract->data()
            + $this->dehydrateEmbeddableData($embeddableList, $embeddableSpecifications)
            + $referencedField;

        return $entity instanceof Augmentation
            ? Record::previouslyLoaded($aggregateRootRecordData, $entity->stateHash())
            : Record::new($aggregateRootRecordData);
    }

    private function checkEmbeddableList(string $mapperClassName, array $embeddableList, array $specifications): void
    {

        $embeddableCount = count($embeddableList);
        $specificationCount = count($specifications);

        if ($specificationCount !== $embeddableCount) {
            throw new RuntimeException(
                sprintf(
                    'Embeddable count (%d) no equals to embeddable specification count (%d) in extract of mapper %s',
                    $embeddableCount,
                    $specificationCount,
                    $mapperClassName
                )
            );
        }

        foreach ($specifications as $embeddableKey => $specification) {
            if (! isset($embeddableList[$embeddableKey])) {
                throw new RuntimeException(
                    sprintf(
                        'Embeddable %s not found in extract of mapper %s',
                        $embeddableKey,
                        $mapperClassName
                    )
                );
            }
        }
    }

    /**
     * @param object[] $embeddableList
     * @param EmbeddableSpecification[] $specifications
     */
    private function dehydrateEmbeddableData(array $embeddableList, array $specifications): array
    {
        $embeddableRecordData = [];
        foreach ($specifications as $embeddableKey => $specification) {
            $mapper = $this->mappers->embeddedMapper($specification->className());

            $embeddableRecordData += $this->addDataKeyPrefix(
                $mapper->dehydrate($embeddableList[$embeddableKey])->data(),
                $specification->prefix()
            );
        }

        return $embeddableRecordData;
    }

    private function addDataKeyPrefix(array $data, string $prefix): array
    {
        return array_combine(
            array_map(
                fn(string $key): string => $prefix . $key,
                array_keys($data)
            ),
            array_values($data)
        );
    }
}