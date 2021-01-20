<?php declare(strict_types=1);
namespace Naisdevice\Jita\Controllers;

use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Statement;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ApiController
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function requests(Request $request, Response $response): Response
    {
        $response->getBody()->write((string) json_encode(['requests' => array_map(fn (array $row): array => [
            'created' => $row['created'],
            'gateway' => $row['gateway'],
            'user_id' => $row['user_id'],
            'expires' => $row['expires'],
            'revoked' => $row['revoked'],
            'reason'  => $row['reason'],
        ], $this->connection->fetchAllAssociative(
            'SELECT created, gateway, user_id, expires, reason, revoked FROM requests ORDER BY id DESC',
        ))]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get gateway requests
     *
     * @param Request $request
     * @param Response $response
     * @param array{gateway:string} $params
     */
    public function gatewayAccess(Request $request, Response $response, array $params): Response
    {
        $rows = $this->getAccessRows('gateway = ?', $params['gateway']);
        $response->getBody()->write((string) json_encode($rows));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get user requests
     *
     * @param Request $request
     * @param Response $response
     * @param array{userId:string} $params
     */
    public function userAccess(Request $request, Response $response, array $params): Response
    {
        $rows = $this->getAccessRows('user_id = ?', (string) $params['userId']);
        $response->getBody()->write((string) json_encode($rows));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get access rows
     *
     * @param string $where Extra WHERE clause
     * @param string $param Parameter used in the WHERE clause
     * @return array<int,array{user_id:string,gateway:string,expires:string,ttl:int}>
     */
    private function getAccessRows(string $where, string $param): array
    {
        /** @var Statement */
        $stmt = $this->connection
            ->createQueryBuilder()
            ->select(['user_id', 'gateway', 'expires'])
            ->from('requests')
            ->where($where)
            ->andWhere('expires > NOW()')
            ->andWhere('revoked IS NULL')
            ->setParameter(0, $param)
            ->execute();

        /** @var array<int,array{user_id:string,gateway:string,expires:string}> */
        $rows = $stmt->fetchAllAssociative();

        /** @var array<int,array{user_id:string,gateway:string,expires:string,ttl:int}> */
        return array_map(fn (array $row): array => [
            'user_id' => $row['user_id'],
            'gateway' => $row['gateway'],
            'expires' => $row['expires'],
            'ttl'     => (new DateTime((string) $row['expires']))->getTimestamp() - (new DateTime('now', new DateTimeZone('UTC')))->getTimestamp(),
        ], $rows);
    }
}
