<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm;

enum Element
{
    case AggregateRoot;
    case Entity;
    case Embeddable;

    public function mayContainEntities(): bool
    {
        return match ($this) {
            self::AggregateRoot => true,
            self::Entity, self::Embeddable => false
        };
    }

    public function mayContainEmbeddable(): bool
    {
        return match ($this) {
            self::AggregateRoot, self::Entity => true,
            self::Embeddable => false
        };
    }
}