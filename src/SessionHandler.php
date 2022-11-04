<?php declare(strict_types=1);
namespace Naisdevice\Jita;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use SessionHandlerInterface;

class SessionHandler implements SessionHandlerInterface
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $sessionId): string|false
    {
        try {
            /** @var array{data:string,last_activity:int}|false */
            $session = $this->connection->fetchAssociative(<<<SQL
                SELECT data, last_activity
                FROM sessions
                WHERE id = :id
            SQL, [
                'id' => $sessionId,
            ]);
        } catch (Exception $e) {
            return '';
        }


        if (false === $session) {
            return '';
        }

        return $this->hasExpired($session['last_activity'])
            ? ''
            : base64_decode($session['data']);
    }

    public function write(string $sessionId, string $data): bool
    {
        try {
            $this->connection->executeStatement(<<<SQL
                INSERT INTO sessions (id, data, last_activity)
                VALUES (:id, :data, :last_activity)
                ON CONFLICT (id) DO UPDATE
                SET data = :data, last_activity = :last_activity
            SQL, [
                'id'            => $sessionId,
                'data'          => base64_encode($data),
                'last_activity' => time(),
            ]);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    public function destroy(string $sessionId): bool
    {
        try {
            $this->connection->executeStatement(<<<SQL
                DELETE FROM sessions
                WHERE id = :id
            SQL, [
                'id' => $sessionId,
            ]);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    public function gc(int $lifetime): int|false
    {
        try {
            $rows = $this->connection->executeStatement(<<<SQL
                DELETE FROM sessions
                WHERE last_activity <= :lifetime
            SQL, [
                'lifetime' => time() - $lifetime,
            ]);
        } catch (Exception $e) {
            return false;
        }

        return (int) $rows;
    }

    private function getSessionLifetime(): int
    {
        return (int) ini_get('session.gc_maxlifetime');
    }

    private function hasExpired(int $lastActivity): bool
    {
        return $lastActivity < time() - $this->getSessionLifetime();
    }
}
