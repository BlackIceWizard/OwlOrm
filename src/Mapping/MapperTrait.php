<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm\Mapping;

use Closure;
use ReflectionClass;
use RuntimeException;

trait MapperTrait
{
    abstract public function applicableFor(): string;

    abstract public function hydrationClosure(): Closure;

    abstract public function dehydrationClosure(): Closure;

    public function dehydrate(object $object): Extract
    {
        return $this->dehydrationClosure()->bindTo($object, $this->applicableFor())();
    }

    private function instantiateAsIs(): object
    {
        $objectClassName = $this->applicableFor();
        $this->checkObjectClass();

        return (new ReflectionClass($objectClassName))->newInstanceWithoutConstructor();
    }

    private function instantiateAugmentedObject(string $stateHash): object
    {
        $this->checkObjectClass();

        $instantiateExpression = sprintf(
            '$instance = new class () extends %s implements %s {'
            . 'public function __construct(){}'
            . 'public function stateHash(): string { return \'%s\'; }'
            . '};',
            $this->applicableFor(),
            Augmentation::class,
            $stateHash
        );

        eval($instantiateExpression);

        /** @noinspection PhpUndefinedVariableInspection */
        return $instance;
    }

    private function checkObjectClass(): void
    {
        if (! class_exists($this->applicableFor())) {
            throw new RuntimeException(sprintf('"%" not valid class name', $this->applicableFor()));
        }
    }
}