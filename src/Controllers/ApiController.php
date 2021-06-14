<?php declare(strict_types=1);
namespace Naisdevice\Jita\Controllers;

use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Connection;
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
        ], $this->connection->fetchAllAssociative(<<<SQL
            SELECT created, gateway, user_id, expires, reason, revoked
            FROM requests
            ORDER BY id DESC
        SQL))]));

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
        /** @var array<int,array{user_id:string,gateway:string,expires:string}> */
        $rows = $this->connection->fetchAllAssociative(<<<SQL
            SELECT user_id, gateway, expires
            FROM requests
            WHERE gateway = :gateway
            AND expires > NOW()
            AND revoked IS NULL
            ORDER BY id DESC
        SQL, [
            'gateway' => $params['gateway'],
        ]);
        $rows = $this->getAccessRowsWithTtl($rows);
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
        /** @var array<int,array{user_id:string,gateway:string,expires:string}> */
        $rows = $this->connection->fetchAllAssociative(<<<SQL
            SELECT user_id, gateway, expires
            FROM requests
            WHERE user_id = :user_id
            AND expires > NOW()
            AND revoked IS NULL
            ORDER BY id DESC
        SQL, [
            'user_id' => $params['userId'],
        ]);
        $rows = $this->getAccessRowsWithTtl($rows);
        $response->getBody()->write((string) json_encode($rows));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get access rows
     *
     * @param array<int,array{user_id:string,gateway:string,expires:string}> $rows
     * @return array<int,array{user_id:string,gateway:string,expires:string,ttl:int}>
     */
    private function getAccessRowsWithTtl(array $rows): array
    {
        $now = (new DateTime('now', new DateTimeZone('UTC')))->getTimestamp();

        /** @var array<int,array{user_id:string,gateway:string,expires:string,ttl:int}> */
        return array_map(fn (array $row): array => [
            'user_id' => $row['user_id'],
            'gateway' => $row['gateway'],
            'expires' => $row['expires'],
            'ttl'     => (new DateTime((string) $row['expires']))->getTimestamp() - $now,
        ], $rows);
    }
}
