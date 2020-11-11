<?php declare(strict_types=1);
namespace Naisdevice\Jita\Middleware;

use Psr\Http\{
    Message\ResponseInterface as Response,
    Message\ServerRequestInterface as Request,
    Server\RequestHandlerInterface as RequestHandler,
};
use RuntimeException;

class EnvironmentValidation {
    /** @var array<string,string> */
    private array $env;

    /**
     * Class constructor
     *
     * @param array<string,string> $env
     */
    public function __construct(array $env) {
        $this->env = $env;
    }

    /**
     * Validate environment variables
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @throws RuntimeException
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler) : Response {
        $missing = [];

        foreach ([
            'ISSUER_ENTITY_ID',
            'LOGIN_URL',
            'LOGOUT_URL',
            'SAML_CERT',
            'API_PASSWORD',
            'DB_DSN',
        ] as $required) {
            if (empty($this->env[$required])) {
                $missing[] = $required;
            }
        }

        if (!empty($missing)) {
            throw new RuntimeException(sprintf('Missing required environment variable(s): %s', join(', ', $missing)));
        }

        return $handler->handle($request);
    }
}