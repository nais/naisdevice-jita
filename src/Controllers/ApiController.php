<?php declare(strict_types=1);
namespace Naisdevice\Jita\Controllers;

use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\{
    ResponseInterface as Response,
    ServerRequestInterface as Request,
};

class ApiController {
    private Connection $connection;

    public function __construct(Connection $connection) {
        $this->connection = $connection;
    }

    public function requests(Request $request, Response $response) : Response {
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $response->getBody()->write((string) json_encode(['requests' => array_map(function(array $request) use ($now) : array {
            $request['expired'] = new DateTime((string) $request['expires'], new DateTimeZone('UTC')) < $now;
            return $request;
        }, $this->connection->fetchAllAssociative(
            'SELECT created, gateway, user_id, expires, reason FROM requests ORDER BY id DESC',
        ))]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array{gateway:string,userId:string} $params
     */
    public function access(Request $request, Response $response, array $params) : Response {
        ['gateway' => $gateway, 'userId' => $userId] = $params;

        $access = $this->connection->fetchAssociative(
            'SELECT id FROM requests WHERE gateway = ? AND user_id = ? AND expires > NOW() LIMIT 1',
            [$gateway, $userId]
        );

        $response->getBody()->write((string) json_encode(['access' => false !== $access]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
