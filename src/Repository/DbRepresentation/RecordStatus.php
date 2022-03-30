<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm\Repository\DbRepresentation;

enum RecordStatus
{
    case JustLoaded;
    case New;
    case NotChanged;
    case Changed;
}