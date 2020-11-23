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
                '{"created":"2020-11-17 20:12:32+00","gateway":"gw-1","user_id":"user-id-1","expires":"2333-11-17 21:12:32+00","expired":false,"reason":"some reason"},' .
                '{"created":"2020-11-17 20:12:35+00","gateway":"gw-2","user_id":"user-id2","expires":"2020-11-17 21:12:35+00","expired":true,"reason":"some other reason"}' .
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
}
