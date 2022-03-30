<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm\Specification;

interface EntitySpecification
{
    /**
     * @return class-string
     */
    public function className(): string;

    public function table(): string;

    public function referencedField();
}