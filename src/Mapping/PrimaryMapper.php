<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm\Mapping;

use RiverRing\OwlOrm\Specification\EmbeddableSpecification;

interface PrimaryMapper extends Mapper
{
    /**
     * @return EmbeddableSpecification[]
     */
    public function embeddable(): array;

    public function hydrate(Extract $extract, string $stateHash): object;
}