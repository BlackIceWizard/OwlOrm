<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm\Dbal\Pdo;

use PDO;
use PDOException;
use RiverRing\OwlOrm\Dbal\Pdo\PdoFactory;

final class LazyPdoProvider implements PdoProvider
{
    private ?PDO $pdo = null;

    private string $dsn;
    private string $user;
    private string $pass;

    private PdoFactory $factory;

    public function __construct(PdoFactory $factory, string $dsn, string $user, string $pass)
    {
        $this->dsn = $dsn;
        $this->user = $user;
        $this->pass = $pass;
        $this->factory = $factory;
    }

    private function connect(): void
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $this->pdo = $this->factory->new($this->dsn, $this->user, $this->pass, $options);
    }

    public function provide(): PDO
    {
        if (! $this->pdo) {
            $this->connect();
        }

        return $this->pdo;
    }
}