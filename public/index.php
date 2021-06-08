<?php declare(strict_types=1);
namespace Naisdevice\Jita;

use DI\Container;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Naisdevice\Jita\Controllers\ApiController;
use Naisdevice\Jita\Controllers\IndexController;
use Naisdevice\Jita\Controllers\MetricsController;
use Naisdevice\Jita\Controllers\SamlController;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\APC;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Flash\Messages as FlashMessages;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Throwable;
use Tuupola\Middleware\HttpBasicAuthentication;
use Twig\Extension\CoreExtension;
use Twig\Extension\DebugExtension;

require __DIR__ . '/../vendor/autoload.php';

define('DEBUG', '1' === env('DEBUG'));

$connection = DriverManager::getConnection(['url' => env('DB_URL')]);
$sessionHandler = new SessionHandler($connection);

session_set_save_handler($sessionHandler);
session_set_cookie_params([
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'None',
]);
session_start();

// Create and populate container
$container = new Container();
$container->set(Connection::class, $connection);
$container->set(SessionHandler::class, $sessionHandler);
$container->set(Session::class, fn () => new Session());
$container->set(Twig::class, function () {
    $twig = Twig::create(__DIR__ . '/../templates', [
        'debug' => DEBUG,
    ]);

    /** @var CoreExtension */
    $core = $twig->getEnvironment()->getExtension(CoreExtension::class);
    $core->setDateFormat('d/m/Y, H:i:s');
    $core->setTimezone('Europe/Oslo');

    if (DEBUG) {
        $twig->addExtension(new DebugExtension());
    }

    return $twig;
});
$container->set(FlashMessages::class, fn () => new FlashMessages());
$container->set(SamlResponseValidator::class, fn () => new SamlResponseValidator(env('SAML_CERT')));
$container->set(IndexController::class, function (ContainerInterface $c) {
    /** @var Twig */
    $twig = $c->get(Twig::class);

    /** @var Session */
    $session = $c->get(Session::class);

    /** @var Connection */
    $connection = $c->get(Connection::class);

    /** @var FlashMessages */
    $flashMessages = $c->get(FlashMessages::class);

    /** @var CollectorRegistry */
    $collectorRegistry = $c->get(CollectorRegistry::class);

    return new IndexController($twig, $session, $connection, $flashMessages, env('LOGIN_URL'), env('ISSUER_ENTITY_ID'), $collectorRegistry);
});
$container->set(CollectorRegistry::class, function () {
    return new CollectorRegistry(new APC(), false);
});
$container->set(MetricsController::class, function (ContainerInterface $c): MetricsController {
    /** @var CollectorRegistry */
    $collectorRegistry = $c->get(CollectorRegistry::class);

    return new MetricsController($collectorRegistry);
});
$container->set(SamlController::class, function (ContainerInterface $c) {
    /** @var Session */
    $session = $c->get(Session::class);

    /** @var SamlResponseValidator */
    $validator = $c->get(SamlResponseValidator::class);

    return new SamlController($session, $validator, env('LOGOUT_URL'));
});
$container->set(ApiController::class, function (ContainerInterface $c) {
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
    'realm'  => 'API',
    'secure' => false,
    'users'  => [
        'naisdevice-jita' => env('API_PASSWORD'),
    ],
]));
$app->add(TwigMiddleware::createFromContainer($app, Twig::class));
$app->add(new Middleware\RemoveDuplicateAuthHeader());
$app->add(new Middleware\EnvironmentValidation(getenv()));
$app
    ->addErrorMiddleware(DEBUG, true, true)
    ->setDefaultErrorHandler(function (Request $request, Throwable $exception, bool $displayErrorDetails) use ($app) {
        /** @var ContainerInterface */
        $container = $app->getContainer();

        /** @var CollectorRegistry */
        $collectorRegistry = $container->get(CollectorRegistry::class);
        $collectorRegistry
            ->getOrRegisterCounter(MetricsController::NS, 'error_counter', 'number of failed jita requests')
            ->incBy(1);

        /** @var Twig */
        $twig = $container->get(Twig::class);

        $statusCode = 500;
        $exceptionCode = (int) $exception->getCode();

        if ($exceptionCode >= 400 && $exceptionCode < 600) {
            // Use the code from the exception if it is in the 400-599 range
            $statusCode = $exceptionCode;
        }

        return $twig->render($app->getResponseFactory()->createResponse($statusCode), 'error.html', [
            'errorMessage' => $exception->getMessage(),
        ]);
    });

// Routes
$app->get('/', IndexController::class . ':index');
$app->post('/createRequest', IndexController::class . ':createRequest');
$app->post('/revokeAccess', IndexController::class . ':revokeAccess');
$app->post('/saml/acs', SamlController::class . ':acs');
$app->get('/saml/logout', SamlController::class . ':logout');
$app->get('/api/v1/requests', ApiController::class . ':requests');
$app->get('/api/v1/gatewayAccess/{gateway}', ApiController::class . ':gatewayAccess');
$app->get('/api/v1/userAccess/{userId}', ApiController::class . ':userAccess');
$app->get('/metrics', MetricsController::class . ':metrics');
$app->get('/isAlive', fn (Request $request, Response $response): Response => $response);
$app->get('/isReady', fn (Request $request, Response $response): Response => $response);

// Run the app
$app->run();
