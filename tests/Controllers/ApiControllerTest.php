<?php declare(strict_types=1);
namespace Naisdevice\Jita\Controllers;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Statement;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\StreamInterface;

/**
 * @coversDefaultClass Naisdevice\Jita\Controllers\ApiController
 */
class ApiControllerTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::requests
     */
    public function testWillRenderRequests(): void
    {
        $connection = $this->createConfiguredMock(Connection::class, [
            'fetchAllAssociative' => [
                [
                    'created' => '2020-11-17 20:12:32+00',
                    'gateway' => 'gw-1',
                    'user_id' => 'user-id-1',
                    'expires' => '2333-11-17 21:12:32+00',
                    'reason'  => 'some reason',
                    'revoked' => null,
                ],
                [
                    'created' => '2020-11-17 20:12:35+00',
                    'gateway' => 'gw-2',
                    'user_id' => 'user-id2',
                    'expires' => '2020-11-17 21:12:35+00',
                    'reason'  => 'some other reason',
                    'revoked' => '2020-11-17 20:15:35+00',
                ],
            ],
        ]);
        $controller = new ApiController($connection);
        $body = $this->createMock(StreamInterface::class);
        $body
            ->expects($this->once())
            ->method('write')
            ->with(
                '{"requests":[' .
                '{"created":"2020-11-17 20:12:32+00","gateway":"gw-1","user_id":"user-id-1","expires":"2333-11-17 21:12:32+00","revoked":null,"reason":"some reason"},' .
                '{"created":"2020-11-17 20:12:35+00","gateway":"gw-2","user_id":"user-id2","expires":"2020-11-17 21:12:35+00","revoked":"2020-11-17 20:15:35+00","reason":"some other reason"}' .
                ']}'
            );

        $request = $this->createMock(Request::class);
        $modifiedResponse = $this->createMock(Response::class);
        $response = $this->createConfiguredMock(Response::class, [
            'getBody' => $body,
        ]);
        $response
            ->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'application/json')
            ->willReturn($modifiedResponse);

        $this->assertSame($modifiedResponse, $controller->requests($request, $response));
    }

    /**
     * @covers ::gatewayAccess
     * @covers ::getAccessRows
     */
    public function testCanGetGatewayAccess(): void
    {
        $queryBuilder = $this->createConfiguredMock(QueryBuilder::class, [
            'select'   => $this->returnSelf(),
            'from'     => $this->returnSelf(),
            'andWhere' => $this->returnSelf(),
            'execute'  => $this->createConfiguredMock(Statement::class, [
                'fetchAllAssociative' => [
                    [
                        'user_id' => 'abc-123',
                        'gateway' => 'some-gw',
                        'expires' => '2040-11-23 10:07:34+00',
                    ],
                    [
                        'user_id' => 'abc-456',
                        'gateway' => 'some-gw',
                        'expires' => '2040-11-23 10:07:34+00',
                    ],
                ],
            ]),
        ]);

        $queryBuilder
            ->expects($this->once())
            ->method('where')
            ->with('gateway = ?')
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with(0, 'some-gw')
            ->willReturnSelf();

        $connection = $this->createConfiguredMock(Connection::class, [
            'createQueryBuilder' => $queryBuilder,
        ]);

        $modifiedResponse = $this->createMock(Response::class);

        $body = $this->createMock(StreamInterface::class);
        $body
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function (string $json): bool {
                /** @var array<int,array{user_id:string,gateway:string,expires:string,ttl?:mixed}> */
                $body = json_decode($json, true);

                return
                    2 === count($body) &&
                    array_key_exists('ttl', $body[0]) &&
                    array_key_exists('ttl', $body[1]) &&
                    is_int($body[0]['ttl']) &&
                    is_int($body[1]['ttl']) &&
                    '2040-11-23 10:07:34+00' === $body[0]['expires'] &&
                    '2040-11-23 10:07:34+00' === $body[1]['expires'] &&
                    'some-gw' === $body[0]['gateway'] &&
                    'some-gw' === $body[1]['gateway'] &&
                    'abc-123' === $body[0]['user_id'] &&
                    'abc-456' === $body[1]['user_id'];
            }));

        $response = $this->createConfiguredMock(Response::class, [
            'getBody' => $body,
        ]);

        $response
            ->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'application/json')
            ->willReturn($modifiedResponse);

        $this->assertSame(
            $modifiedResponse,
            (new ApiController($connection))->gatewayAccess(
                $this->createMock(Request::class),
                $response,
                ['gateway' => 'some-gw'],
            )
        );
    }

    /**
     * @covers ::userAccess
     * @covers ::getAccessRows
     */
    public function testCanGetUserAccess(): void
    {
        $queryBuilder = $this->createConfiguredMock(QueryBuilder::class, [
            'select'   => $this->returnSelf(),
            'from'     => $this->returnSelf(),
            'andWhere' => $this->returnSelf(),
            'execute'  => $this->createConfiguredMock(Statement::class, [
                'fetchAllAssociative' => [
                    [
                        'user_id' => 'abc-123',
                        'gateway' => 'some-gw-1',
                        'expires' => '2040-11-23 10:07:34+00',
                    ],
                    [
                        'user_id' => 'abc-123',
                        'gateway' => 'some-gw-2',
                        'expires' => '2040-11-23 10:07:34+00',
                    ],
                ],
            ]),
        ]);

        $queryBuilder
            ->expects($this->once())
            ->method('where')
            ->with('user_id = ?')
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with(0, 'abc-123')
            ->willReturnSelf();

        $connection = $this->createConfiguredMock(Connection::class, [
            'createQueryBuilder' => $queryBuilder,
        ]);

        $modifiedResponse = $this->createMock(Response::class);

        $body = $this->createMock(StreamInterface::class);
        $body
            ->expects($this->once())
            ->method('write')
            ->with($this->callback(function (string $json): bool {
                /** @var array<int,array{user_id:string,gateway:string,expires:string,ttl?:mixed}> */
                $body = json_decode($json, true);

                return
                    2 === count($body) &&
                    array_key_exists('ttl', $body[0]) &&
                    array_key_exists('ttl', $body[1]) &&
                    is_int($body[0]['ttl']) &&
                    is_int($body[1]['ttl']) &&
                    '2040-11-23 10:07:34+00' === $body[0]['expires'] &&
                    '2040-11-23 10:07:34+00' === $body[1]['expires'] &&
                    'some-gw-1' === $body[0]['gateway'] &&
                    'some-gw-2' === $body[1]['gateway'] &&
                    'abc-123' === $body[0]['user_id'] &&
                    'abc-123' === $body[1]['user_id'];
            }));

        $response = $this->createConfiguredMock(Response::class, [
            'getBody' => $body,
        ]);

        $response
            ->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'application/json')
            ->willReturn($modifiedResponse);

        $this->assertSame(
            $modifiedResponse,
            (new ApiController($connection))->userAccess(
                $this->createMock(Request::class),
                $response,
                ['userId' => 'abc-123'],
            )
        );
    }
}
