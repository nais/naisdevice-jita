<?php declare(strict_types=1);
namespace Naisdevice\Jita;

use DI\Container;
use Doctrine\DBAL\{
    DriverManager,
    Connection,
};
use Naisdevice\Jita\Controllers\{
    ApiController,
    IndexController,
    SamlController,
};
use Psr\{
    Container\ContainerInterface,
    Http\Message\ResponseInterface as Response,
    Http\Message\ServerRequestInterface as Request,
};
use Slim\{
    Factory\AppFactory,
    Flash\Messages as FlashMessages,
    Views\Twig,
    Views\TwigMiddleware,
};
use Throwable;
use Tuupola\Middleware\HttpBasicAuthentication;
use Twig\Extension\DebugExtension;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Get env var as string
 *
 * @param string $key
 * @return string
 */
function env(string $key) : string {
    return trim((string) getenv($key));
}

define('DEBUG', '1' === env('DEBUG'));

// Create and populate container
$container = new Container();
$container->set(Connection::class, fn() => DriverManager::getConnection([
    'url' => env('DB_URL'),
]));
$container->set(Twig::class, function() {
    $twig = Twig::create(__DIR__ . '/../templates', [
        'debug' => DEBUG,
    ]);

    if (DEBUG) {
        $twig->addExtension(new DebugExtension());
    }

    return $twig;
});
$container->set(Session::class, (new Session())->start());
$container->set(FlashMessages::class, new FlashMessages()); // Must be initialized after the session entry on the line above
$container->set(SamlResponseValidator::class, fn() => new SamlResponseValidator(env('SAML_CERT')));
$container->set(Gateways::class, fn() => new Gateways());
$container->set(IndexController::class, function(ContainerInterface $c) {
    /** @var Twig */
    $twig = $c->get(Twig::class);

    /** @var Session */
    $session = $c->get(Session::class);

    /** @var Connection */
    $connection = $c->get(Connection::class);

    /** @var FlashMessages */
    $flashMessages = $c->get(FlashMessages::class);

    /** @var Gateways */
    $gateways = $c->get(Gateways::class);

    return new IndexController($twig, $session, $connection, $flashMessages, env('LOGIN_URL'), env('ISSUER_ENTITY_ID'), $gateways);
});
$container->set(SamlController::class, function(ContainerInterface $c) {
    /** @var Session */
    $session = $c->get(Session::class);

    /** @var SamlResponseValidator */
    $validator = $c->get(SamlResponseValidator::class);

    return new SamlController($session, $validator, env('LOGOUT_URL'));
});
$container->set(ApiController::class, function(ContainerInterface $c) {
    /** @var Connection */
    $connection = $c->get(Connection::class);

    return new ApiController($connection);
});

AppFactory::setContainer($container);
$app = AppFactory::create();

// Register middleware
$app->addBodyParsingMiddleware();
$app->add(new HttpBasicAuthentication([
    'path'   => '/api',
    'secure' => false,
    'users'  => [
        'naisdevice-jita' => env('API_PASSWORD'),
    ],
]));
$app->add(TwigMiddleware::createFromContainer($app, Twig::class));
$app->add(new Middleware\EnvironmentValidation(getenv()));
$app
    ->addErrorMiddleware(DEBUG, true, true)
    ->setDefaultErrorHandler(function(Request $request, Throwable $exception, bool $displayErrorDetails) use ($app) {
        /** @var ContainerInterface */
        $container = $app->getContainer();

        /** @var Twig */
        $twig = $container->get(Twig::class);

        return $twig->render($app->getResponseFactory()->createResponse(500), 'error.html', [
            'errorMessage' => $displayErrorDetails ? $exception->getMessage() : 'An error occurred',
        ]);
    });

// Routes
$app->get('/', IndexController::class . ':index');
$app->post('/', IndexController::class . ':createRequest');
$app->post('/saml/acs', SamlController::class . ':acs');
$app->get('/saml/logout', SamlController::class . ':logout');
$app->get('/api/v1/requests', ApiController::class . ':requests');
$app->get('/api/v1/access/{gateway}/{userId}', ApiController::class . ':access');
$app->get('/isAlive', fn(Request $request, Response $response) : Response => $response);
$app->get('/isReady', fn(Request $request, Response $response) : Response => $response);

// Run the app
$app->run();
