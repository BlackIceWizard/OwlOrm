<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm\Dbal\Driver;

use Exception;

interface TransactionalRunner
{
    /**
     * @throws Exception
     */
    public function transactional(callable $operation): void;
}