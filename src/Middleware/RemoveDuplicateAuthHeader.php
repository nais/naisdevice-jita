<?php declare(strict_types=1);
namespace Naisdevice\Jita\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class RemoveDuplicateAuthHeader
{
    /**
     * Remove duplicate auth header if present
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        if (1 < count($request->getHeader('authorization'))) {
            $request = $request->withHeader('authorization', $request->getHeader('authorization')[0]);
        }

        return $handler->handle($request);
    }
}
