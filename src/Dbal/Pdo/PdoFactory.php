<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm\Dbal\Pdo;

use PDO;

interface PdoFactory
{
    public function new(string $dsn, string $user, string $pass, array $options): PDO;
}