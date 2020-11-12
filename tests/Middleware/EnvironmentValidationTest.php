<?php declare(strict_types=1);
namespace Naisdevice\Jita\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Http\{
    Message\ResponseInterface,
    Message\ServerRequestInterface,
    Server\RequestHandlerInterface,
};
use RuntimeException;

/**
 * @coversDefaultClass Naisdevice\Jita\Middleware\EnvironmentValidation
 */
class EnvironmentValidationTest extends TestCase {
    /**
     * @return array<string,array{0:array<string,string>,1:string}>
     */
    public function getEnvVars() : array {
        return [
            'no vars' => [
                [],
                'Missing required environment variable(s): ISSUER_ENTITY_ID, LOGIN_URL, LOGOUT_URL, SAML_CERT, API_PASSWORD, DB_URL',
            ],
            'missing' => [
                [
                    'LOGIN_URL'     => 'some url',
                    'SAML_CERT'     => 'some cert',
                ],
                'Missing required environment variable(s): ISSUER_ENTITY_ID, LOGOUT_URL, API_PASSWORD, DB_URL',
            ],
        ];
    }

    /**
     * @dataProvider getEnvVars
     * @covers ::__construct
     * @covers ::__invoke
     * @param array<string,string> $vars
     * @param string $error
     */
    public function testFailsOnMissingValue(array $vars, string $error) : void {
        $this->expectExceptionObject(new RuntimeException($error));
        (new EnvironmentValidation($vars))(
            $this->createMock(ServerRequestInterface::class),
            $this->createMock(RequestHandlerInterface::class)
        );
    }

    /**
     * @covers ::__invoke
     */
    public function testHandleResponseOnSuccess() : void {
        $request  = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler  = $this->createMock(RequestHandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        $middleware = new EnvironmentValidation([
            'ISSUER_ENTITY_ID'  => 'some id',
            'LOGIN_URL'         => 'some url',
            'LOGOUT_URL'        => 'some other url',
            'SAML_CERT'         => 'some cert',
            'API_PASSWORD'      => 'password',
            'DB_URL'            => 'some url',
        ]);
        $this->assertSame($response, $middleware($request, $handler));
    }
}
