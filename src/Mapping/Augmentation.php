<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm\Mapping;

interface Augmentation
{
    public function stateHash(): string;
}