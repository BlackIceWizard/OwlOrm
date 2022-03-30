<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm\Mapping;

interface EmbeddedMapper extends Mapper
{
    public function hydrate(Extract $extract): object;
}