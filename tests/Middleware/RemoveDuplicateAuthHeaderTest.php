<?php declare(strict_types=1);

namespace Naisdevice\Jita\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(RemoveDuplicateAuthHeader::class)]
class RemoveDuplicateAuthHeaderTest extends TestCase
{
    public function testCanRemoveDuplicateAuthHeaders(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $modifiedRequest = $this->createMock(ServerRequestInterface::class);
        $request  = $this->createMock(ServerRequestInterface::class);
        $request
            ->method('getHeader')
            ->with('authorization')
            ->willReturn([
                'Basic foo',
                'Basic bar',
            ]);

        $request
            ->expects($this->once())
            ->method('withHeader')
            ->with('authorization', 'Basic foo')
            ->willReturn($modifiedRequest);

        $handler  = $this->createMock(RequestHandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->with($modifiedRequest)
            ->willReturn($response);

        $middleware = new RemoveDuplicateAuthHeader();
        $this->assertSame($response, $middleware($request, $handler));
    }
}
