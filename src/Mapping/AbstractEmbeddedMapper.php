<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm\Mapping;

abstract class AbstractEmbeddedMapper implements EmbeddedMapper
{
    use MapperTrait;

    public function hydrate(Extract $extract): object
    {
        $object = $this->instantiateAsIs();

        $this->hydrationClosure()->bindTo($object, $this->applicableFor())($extract);

        return $object;
    }
}