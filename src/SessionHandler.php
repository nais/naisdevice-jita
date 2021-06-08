<?php declare(strict_types=1);
namespace Naisdevice\Jita;

use Doctrine\DBAL\Connection;
use SessionHandlerInterface;

class SessionHandler implements SessionHandlerInterface
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Initialize session
     *
     * @param string $savePath
     * @param string $sessionName
     * @return true
     */
    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    /**
     * Close the session
     *
     * @return bool
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * Read session data
     *
     * @param string $sessionId
     * @return string|false
     */
    public function read($sessionId)
    {
        /** @var array{data:string,last_activity:int}|false */
        $session = $this->connection->fetchAssociative(<<<SQL
            SELECT data, last_activity
            FROM sessions
            WHERE id = :id
        SQL, [
            'id' => $sessionId,
        ]);

        if (false === $session) {
            return '';
        }

        return $this->hasExpired($session['last_activity'])
            ? ''
            : base64_decode($session['data']);
    }

    /**
     * Write session data
     *
     * @param string $sessionId
     * @param string $data
     * @return bool
     */
    public function write($sessionId, $data): bool
    {
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

        return true;
    }

    /**
     * Destroy the session
     *
     * @param string $sessionId
     * @return bool
     */
    public function destroy($sessionId): bool
    {
        $this->connection->executeStatement(<<<SQL
            DELETE FROM sessions
            WHERE id = :id
        SQL, [
            'id' => $sessionId,
        ]);

        return true;
    }

    /**
     * Clean up old session
     *
     * @param int $lifetime
     * @return int|false Returns the number of deleted sessions on success, or false on failure
     */
    public function gc($lifetime)
    {
        return $this->connection->executeStatement(<<<SQL
            DELETE FROM sessions
            WHERE last_activity <= :lifetime
        SQL, [
            'lifetime' => time() - $lifetime,
        ]);
    }

    /**
     * Get the session lifetime
     *
     * @return int
     */
    private function getSessionLifetime(): int
    {
        return (int) ini_get('session.gc_maxlifetime');
    }

    /**
     * Determine if the session has expired or not
     *
     * @param int $lastActivity
     * @return bool
     */
    private function hasExpired(int $lastActivity): bool
    {
        return $lastActivity < time() - $this->getSessionLifetime();
    }
}
