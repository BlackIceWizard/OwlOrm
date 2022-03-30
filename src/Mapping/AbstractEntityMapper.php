<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm\Mapping;

abstract class AbstractEntityMapper implements PrimaryMapper
{
    use MapperTrait;

    public function hydrate(Extract $extract, string $stateHash): object
    {
        $object = $this->instantiateAugmentedObject($stateHash);

        $this->hydrationClosure()->bindTo($object, $this->applicableFor())($extract);

        return $object;
    }
}