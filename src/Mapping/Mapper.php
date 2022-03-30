<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm\Mapping;

use Closure;

interface Mapper
{
    public function dehydrate(object $object): Extract;

    /**
     * @return class-string
     */
    public function applicableFor(): string;

    public function hydrationClosure(): Closure;

    public function dehydrationClosure(): Closure;
}