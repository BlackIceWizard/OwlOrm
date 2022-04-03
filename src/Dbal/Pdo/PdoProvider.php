<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm\Dbal\Pdo;

use PDO;

interface PdoProvider
{
    public function provide(): PDO;
}