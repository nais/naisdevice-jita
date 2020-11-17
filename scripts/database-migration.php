<?php declare(strict_types=1);
namespace Naisdevice\Jita;

use Doctrine\DBAL\DriverManager;
use Monolog\{
    Formatter\LineFormatter,
    Handler\StreamHandler,
    Logger,
};

require __DIR__ . '/../vendor/autoload.php';

$logger = new Logger('naisdevice-jita-db-migrations', [
    (new StreamHandler('php://stdout'))->setFormatter(new LineFormatter(null, null, false, true))
]);

if ('' === env('DB_URL')) {
    $logger->alert('Missing required environment variable DB_URL');
    exit(1);
}

(new DatabaseMigrations(
    DriverManager::getConnection(['url' => env('DB_URL')]),
    __DIR__ . '/schemas',
    $logger,
))->migrate();
