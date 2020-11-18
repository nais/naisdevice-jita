<?php declare(strict_types=1);
namespace Naisdevice\Jita\Controllers;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\{
    ServerRequestInterface as Request,
    StreamInterface,
    ResponseInterface as Response,
};

/**
 * @coversDefaultClass Naisdevice\Jita\Controllers\ApiController
 */
class ApiControllerTest extends TestCase {
    /**
     * @covers ::__construct
     * @covers ::requests
     */
    public function testWillRenderRequests() : void {
        $connection = $this->createConfiguredMock(Connection::class, [
            'fetchAllAssociative' => [
                [
                    'created' => '2020-11-17 20:12:32+00',
                    'gateway' => 'gw-1',
                    'user_id' => 'user-id-1',
                    'expires' => '2333-11-17 21:12:32+00',
                    'reason'  => 'some reason',
                ],
                [
                    'created' => '2020-11-17 20:12:35+00',
                    'gateway' => 'gw-2',
                    'user_id' => 'user-id2',
                    'expires' => '2020-11-17 21:12:35+00',
                    'reason'  => 'some other reason',
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
                '{"created":"2020-11-17 20:12:32+00","gateway":"gw-1","user_id":"user-id-1","expires":"2333-11-17 21:12:32+00","reason":"some reason","expired":false},' .
                '{"created":"2020-11-17 20:12:35+00","gateway":"gw-2","user_id":"user-id2","expires":"2020-11-17 21:12:35+00","reason":"some other reason","expired":true}' .
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
     * @return array<string,array{gateway:string,userId:string,dbResult:bool|array{id:int},expectedResponseBody:string}>
     */
    public function getAccessData() : array {
        return [
            'record not found' => [
                'gateway'              => 'some-gateway',
                'userId'               => 'some-id',
                'dbResult'             => false,
                'expectedResponseBody' => '{"access":false}',
            ],
            'record found' => [
                'gateway'              => 'some-gateway',
                'userId'               => 'some-id',
                'dbResult'             => ['id' => 123],
                'expectedResponseBody' => '{"access":true}',
            ]
        ];
    }

    /**
     * @dataProvider getAccessData
     * @covers ::__construct
     * @covers ::access
     * @param bool|array{id:int} $dbResult
     */
    public function testWillRenderAccessCheck(string $gateway, string $userId, $dbResult, string $expectedResponseBody) : void {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with($this->isType('string'), $this->callback(fn(array $params) : bool =>
                $params[0] === $gateway &&
                $params[1] === $userId
            ))
            ->willReturn($dbResult);

        $controller = new ApiController($connection);
        $body = $this->createMock(StreamInterface::class);
        $body
            ->expects($this->once())
            ->method('write')
            ->with($expectedResponseBody);

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

        $this->assertSame($modifiedResponse, $controller->access($request, $response, ['gateway' => $gateway, 'userId' => $userId]));
    }
}
