<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm\Tests\Dbal\Pdo;

use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RiverRing\OwlOrm\Dbal\Pdo\GeneralPdoFactory;
use RiverRing\OwlOrm\Dbal\Pdo\PdoFactory;
use RiverRing\OwlOrm\Dbal\Pdo\LazyPdoProvider;

class PdoProviderTest extends TestCase
{
    private $dsn = 'fakeDsn';
    private $user = 'fakeUser';
    private $pass = 'fakePass';
    private $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    private GeneralPdoFactory|MockObject $factory;

    public function setUp(): void
    {
        $this->factory = self::createMock(PdoFactory::class);
        $this->it = new LazyPdoProvider($this->factory, $this->dsn, $this->user, $this->pass);
    }

    /**
     * @test
     */
    public function it_provides_the_same_instance_every_time(): void
    {
        $this->factory
            ->expects(self::once())
            ->method('new')
            ->with($this->dsn, $this->user, $this->pass, $this->options)
            ->willReturn(new class extends PDO {
                public function __construct()
                {
                }
            });

        $firstInstance = $this->it->provide();
        $secondInstance = $this->it->provide();

        self::assertTrue($firstInstance === $secondInstance);
    }
}
