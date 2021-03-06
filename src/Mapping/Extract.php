<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm\Mapping;

use BadMethodCallException;
use JetBrains\PhpStorm\Pure;
use RiverRing\OwlOrm\Element;

class Extract
{
    private Element $target;
    private array $data;
    private array $entities;
    private array $embeddable;

    private function __construct(Element $target, array $data, array $entities = [], array $embeddable = [])
    {
        $this->target = $target;
        $this->data = $data;
        $this->entities = $entities;
        $this->embeddable = $embeddable;
    }

    public function getTarget(): Element
    {
        return $this->target;
    }

    #[Pure]
    public static function ofAggregateRoot(array $data, array $entities = [], array $embeddable = []): self
    {
        return new self(Element::AggregateRoot, $data, $entities, $embeddable);
    }

    #[Pure]
    public static function ofEntity(array $data, array $embeddable = []): self
    {
        return new self(Element::Entity, $data, [], $embeddable);
    }

    #[Pure]
    public static function ofEmbeddable(array $data): self
    {
        return new self(Element::Embeddable, $data);
    }

    public function data(): array
    {
        return $this->data;
    }

    public function entities(): ?array
    {
        if(! $this->target->mayContainEntities()) {
            throw new BadMethodCallException(sprintf('%s can\'t contain entities', $this->target->name));
        }

        return $this->entities;
    }

    public function embeddable(): ?array
    {
        if(! $this->target->mayContainEmbeddable()) {
            throw new BadMethodCallException(sprintf('%s can\'t contain embeddable', $this->target->name));
        }

        return $this->embeddable;
    }
}