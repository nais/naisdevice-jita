<?php declare(strict_types=1);
namespace Naisdevice\Jita\Controllers;

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
        $response->getBody()->write((string) json_encode(['requests' => array_map(function(array $request) : array {
            $request['created'] = (int) $request['created'];
            $request['expires'] = (int) $request['expires'];
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
            'SELECT * FROM requests WHERE gateway = ? AND user_id = ? AND expires > ? LIMIT 1',
            [$gateway, $userId, time()]
        );

        $response->getBody()->write((string) json_encode(['access' => false !== $access]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
