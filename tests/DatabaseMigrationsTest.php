<?php declare(strict_types=1);

namespace Naisdevice\Jita;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\InvalidArgumentException as DBALInvalidArgumentException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use InvalidArgumentException;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseMigrations::class)]
class DatabaseMigrationsTest extends TestCase
{
    public function testThrowsExceptionOnInvalidDirectory(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException('Not a directory: /foo/bar'));
        new DatabaseMigrations($this->createMock(Connection::class), '/foo/bar');
    }

    public function testDoesNothingOnEmptyMigrationsDirectory(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('executeStatement');

        $this->assertSame(0, (new DatabaseMigrations($connection, __DIR__))->migrate());
    }

    public function testWarningOnMissingMigrationsTable(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->willThrowException($this->createMock(TableNotFoundException::class));

        $logger = $this->createMock(Logger::class);
        $logger
            ->expects($this->once())
            ->method('warning');

        $this->assertSame(0, (new DatabaseMigrations($connection, __DIR__, $logger))->migrate());
    }

    public function testThrowsExceptionOnDatabaseError(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->willThrowException(new DBALInvalidArgumentException('some error'));

        $logger = $this->createMock(Logger::class);
        $logger
            ->expects($this->once())
            ->method('alert')
            ->with('Database connection error, unable to run database migrations: some error');

        $this->assertSame(1, (new DatabaseMigrations($connection, __DIR__, $logger))->migrate());
    }

    public function testCanRunMigrations(): void
    {
        $connection = $this->createConfiguredMock(Connection::class, [
            'fetchOne' => '1',
        ]);
        $expects = $this->exactly(2);
        $connection
            ->expects($expects)
            ->method('executeStatement')
            ->willReturnCallback(
                fn (string $param): int =>
                match ([$expects->numberOfInvocations(), $param]) {
                    [1, 'second'] => 0,
                    [2, 'last'] => 0,
                    default => $this->fail("Unexpected parameter: " . $param)
                },
            );
        $logger = $this->createMock(Logger::class);

        $this->assertSame(0, (new DatabaseMigrations($connection, __DIR__ . '/fixtures/schemas', $logger))->migrate());
    }

    public function testReturnsNonZeroOnErrorDuringMigration(): void
    {
        $connection = $this->createConfiguredMock(Connection::class, [
            'fetchOne' => false,
        ]);
        $expects = $this->exactly(2);
        $connection
            ->expects($expects)
            ->method('executeStatement')
            ->willReturnCallback(function (string $param) use ($expects): int {
                $i = $expects->numberOfInvocations();
                if ($i === 1) {
                    $this->assertSame('first', $param);
                    return 0;
                }

                if ($i === 2) {
                    $this->assertSame('second', $param);
                    throw new DBALInvalidArgumentException('some error');
                }

                $this->fail("Unexpected parameter: " . $param);
            });
        $logger = $this->createMock(Logger::class);
        $logger
            ->expects($this->once())
            ->method('alert')
            ->with('An errur occurred during automatic database migration. Manual action is required: some error');

        $this->assertSame(1, (new DatabaseMigrations($connection, __DIR__ . '/fixtures/schemas', $logger))->migrate());
    }
}
