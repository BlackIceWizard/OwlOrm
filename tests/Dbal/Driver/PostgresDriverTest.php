<?php
declare(strict_types=1);

namespace RiverRing\OwlOrm\Tests\Dbal\Driver;

use ArrayObject;
use Exception;
use Iterator;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RiverRing\OwlOrm\Dbal\Driver\PostgresDriver;
use RiverRing\OwlOrm\Dbal\Pdo\PdoProvider;
use RiverRing\OwlOrm\Repository\DbRepresentation\Record;
use RiverRing\OwlOrm\Repository\DbRepresentation\RecordStatus;

class PostgresDriverTest extends TestCase
{
    private MockObject|PDO $pdo;
    private MockObject|PdoProvider $pdoProvider;
    private PDOStatement|MockObject $pdoStatement;

    private PostgresDriver $it;

    public function setUp(): void
    {
        $this->pdo = self::createMock(PDO::class);
        $this->pdoProvider = self::createMock(PdoProvider::class);
        $this->pdoProvider->expects(self::once())->method('provide')->willReturn($this->pdo);
        $this->pdoStatement = self::createMock(PDOStatement::class);

        $this->it = new PostgresDriver($this->pdoProvider);
    }

    public function executionParamsProvider(): array
    {
        $query = 'SELECT * FROM some;';
        $params = [1 => 'one', 2 => 'two'];

        return [
            'Success query' => [$query, $params, true],
            'Fail query' => [$query, $params, false],
        ];
    }

    /**
     * @test
     * @dataProvider executionParamsProvider
     */
    public function it_executes_query_as_is(string $query, array $params, bool $expectedResult): void
    {
        $this->pdo->expects(self::once())->method('prepare')->with($query)->willReturn($this->pdoStatement);
        $this->pdoStatement->expects(self::once())->method('execute')->with($params)->willReturn($expectedResult);

        $this->it->execute($query, $params);
    }

    public function findOneResultsProvider(): array
    {
        $query = 'SELECT * FROM some;';
        $params = [1 => 'one', 2 => 'two'];

        return [
            'Record data if one record found' => [$query, $params, [['some' => 123]], ['some' => 123]],
            'First record data if multiple record found' => [$query, $params, [['some' => 123], ['any' => 321]], ['some' => 123]],
            'Null if no one record found' => [$query, $params, [null], null],
        ];
    }

    /**
     * @test
     * @dataProvider findOneResultsProvider
     */
    public function it_find_one_results_are(string $query, array $params, array $consecutiveFetchedData, ?array $expectedResult): void
    {
        $this->pdo->expects(self::once())->method('prepare')->with($query)->willReturn($this->pdoStatement);

        $this->pdoStatement
            ->expects(self::once())
            ->method('fetch')
            ->with()
            ->willReturnOnConsecutiveCalls(...$consecutiveFetchedData);

        $result = $this->it->findOne($query, $params);

        self::assertEquals($expectedResult, $result);
    }

    public function findResultsProvider(): array
    {
        $query = 'SELECT * FROM some;';
        $params = [1 => 'one', 2 => 'two'];

        return [
            'All record data' => [$query, $params, (new ArrayObject([['some' => 123]]))->getIterator()],
        ];
    }

    /**
     * @test
     * @dataProvider findResultsProvider
     */
    public function it_find_results_are(string $query, array $params, Iterator $expectedResult): void
    {
        $this->pdo->expects(self::once())->method('prepare')->with($query)->willReturn($this->pdoStatement);

        $this->pdoStatement
            ->expects(self::once())
            ->method('getIterator')
            ->with()
            ->willReturn($expectedResult);

        $result = $this->it->find($query, $params);

        self::assertEquals($expectedResult, $result);
    }

    /**
     * @test
     */
    public function it_compiles_correct_query_when_searching_entities(): void
    {
        $aggregateRootId = 'some_id_value';

        $this->pdo
            ->expects(self::once())
            ->method('prepare')
            ->with('SELECT * FROM "some_table" where "ref_key" = :aggregate_root_id')
            ->willReturn($this->pdoStatement);

        $this->pdoStatement
            ->expects(self::once())
            ->method('execute')
            ->with(self::equalTo(['aggregate_root_id' => $aggregateRootId]));

        $this->pdoStatement
            ->expects(self::once())
            ->method('getIterator')
            ->with()
            ->willReturn((new ArrayObject([]))->getIterator());

        $this->it->findEntitySet($aggregateRootId, 'some_table', 'ref_key');
    }

    /**
     * @test
     */
    public function it_compiles_correct_query_when_searching_entity(): void
    {
        $aggregateRootId = 'some_id_value';

        $this->pdo
            ->expects(self::once())
            ->method('prepare')
            ->with('SELECT * FROM "some_table" where "ref_key" = :aggregate_root_id limit 1')
            ->willReturn($this->pdoStatement);

        $this->pdoStatement
            ->expects(self::once())
            ->method('execute')
            ->with(self::equalTo(['aggregate_root_id' => $aggregateRootId]));

        $this->it->findEntity($aggregateRootId, 'some_table', 'ref_key');
    }

    public function storingVariantsProvider(): array
    {
        return [
            'Create new New record' => [
                'INSERT INTO "some_table" ("pkey", "another_field") VALUES (:pkey, :another_field)',
                ['pkey' => 123, 'another_field' => 312],
                Record::new(['pkey' => 123, 'another_field' => 312]),
            ],
            'Ppdate exists record' => [
                'UPDATE "some_table" SET ("pkey", "another_field") = (:pkey, :another_field) WHERE "pkey" = :pkey_2',
                ['pkey' => 123, 'another_field' => 312, 'pkey_2' => 123],
                Record::previouslyLoaded(['pkey' => 123, 'another_field' => 312], md5('')),
            ]
        ];
    }

    /**
     * @test
     * @dataProvider storingVariantsProvider
     */
    public function it_compiles_correct_query_when_storing(string $sql, array $params, Record $record): void
    {
        $this->pdo
            ->expects(self::once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($this->pdoStatement);

        $this->pdoStatement
            ->expects(self::once())
            ->method('execute')
            ->with($params);

        $this->it->store('some_table', 'pkey', $record);
    }

    /**
     * @test
     */
    public function it_commit_changes_when_no_exceptions(): void
    {
        $this->pdo
            ->expects(self::once())
            ->method('beginTransaction');

        $this->pdo
            ->expects(self::once())
            ->method('commit');

        $this->it->transactional(function () {
        });
    }

    /**
     * @test
     */
    public function it_roll_back_changes_when_exceptions_exists(): void
    {
        $exception = new Exception();

        $this->pdo
            ->expects(self::once())
            ->method('beginTransaction');

        $this->pdo
            ->expects(self::once())
            ->method('rollBack');

        $this->expectExceptionObject($exception);

        $this->it->transactional(function () use ($exception) {
            throw $exception;
        });
    }


}
