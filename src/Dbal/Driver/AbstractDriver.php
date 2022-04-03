<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm\Dbal\Driver;

use Exception;
use Iterator;
use JetBrains\PhpStorm\Pure;
use RiverRing\OwlOrm\Dbal\Pdo\LazyPdoProvider;
use RiverRing\OwlOrm\Dbal\Pdo\PdoProvider;
use RiverRing\OwlOrm\Repository\DbRepresentation\Record;
use RiverRing\OwlOrm\Repository\DbRepresentation\RecordStatus;
use PDO;

abstract class AbstractDriver implements Driver
{
    private PdoProvider $pdoProvider;
    private ?PDO $pdo = null;

    public function __construct(PdoProvider $pdoProvider)
    {
        $this->pdoProvider = $pdoProvider;
    }

    protected function pdo(): PDO
    {
        if (! $this->pdo) {
            $this->pdo = $this->pdoProvider->provide();
        }

        return $this->pdo;
    }
}