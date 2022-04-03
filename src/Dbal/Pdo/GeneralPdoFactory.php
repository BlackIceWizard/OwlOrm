<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm\Dbal\Pdo;

use PDO;
use PDOException;

final class GeneralPdoFactory implements PdoFactory
{
    public function new(string $dsn, string $user, string $pass, array $options): PDO
    {
        try {
            return new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int) $e->getCode());
        }
    }
}