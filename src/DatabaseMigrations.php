<?php declare(strict_types=1);
namespace Naisdevice\Jita;

use Doctrine\DBAL\{
    Connection,
    Exception,
    Exception\TableNotFoundException,
};
use InvalidArgumentException;
use Monolog\Logger;
use RuntimeException;

class DatabaseMigrations {
    const MIGRATION_ERROR   = 1;
    const MIGRATION_SUCCESS = 0;

    private Connection $connection;
    private Logger $logger;

    /**
     * Numerically indexed array with values pointing to files containing transactions
     *
     * @var string[]
     */
    private array $migrations;

    public function __construct(Connection $connection, string $migrationsPath, Logger $logger = null) {
        $migrationsPath = rtrim($migrationsPath, '/');

        $this->logger     = $logger ?: new Logger(__CLASS__);
        $this->connection = $connection;
        $this->migrations = $this->getMigrationsFilesFromPath($migrationsPath);
    }

    /**
     * Migrate the database schema
     *
     * @return int
     */
    public function migrate() : int {
        $currentVersion = $this->getCurrentVersion();

        for ($version = $currentVersion; $version < count($this->migrations); $version++) {
            $migrationPath = $this->migrations[$version];
            $transaction   = trim((string) file_get_contents($migrationPath));

            $this->logger->info(sprintf('Run migration: %s', $migrationPath));

            try {
                $this->connection->executeStatement($transaction);
            } catch (Exception $e) {
                $this->logger->alert(sprintf(
                    'An errur occurred during automatic database migration. Manual action is required: %s',
                    $e->getMessage(),
                ));
                return self::MIGRATION_ERROR;
            }
        }

        return self::MIGRATION_SUCCESS;
    }

    /**
     * Get the current version of the schema
     *
     * @throws RuntimeException
     * @return int
     */
    private function getCurrentVersion() : int {
        try {
            return (int) $this->connection->fetchOne("SELECT MAX(version) FROM migrations");
        } catch (TableNotFoundException $e) {
            $this->logger->warning('Unable to find the migrations table, start from scratch');
            return 0;
        } catch (Exception $e) {
            throw new RuntimeException(
                sprintf('Database connection error, unable to run database migrations: %s', $e->getMessage())
            );
        }
    }

    /**
     * Returns a numerically indexed array with complete paths
     *
     * @see https://github.com/nais/naisdevice-jita/blob/main/scripts/schemas/README.md
     * @param string $dir Directory including migrations SQL scripts
     * @throws InvalidArgumentException
     * @return string[]
     */
    private function getMigrationsFilesFromPath(string $dir) : array {
        if (!is_dir($dir)) {
            throw new InvalidArgumentException(sprintf('Not a directory: %s', $dir));
        }

        /** @var string[] */
        $allFiles = scandir($dir, SCANDIR_SORT_ASCENDING);

        // Only keep specific files
        $migrationFiles = array_values(array_filter(
            $allFiles,
            fn(string $path) : bool => 1 === preg_match('/^[0-9]{4}-.*\.sql$/', $path),
        ));

        // Prepend $dir to get the whole path
        return array_map(
            fn(string $path) : string => $dir . '/' . $path,
            $migrationFiles,
        );
    }
}
