<?php
declare(strict_types=1);

/**
 * Database — Singleton PDO factory.
 * Reads credentials from environment variables (loaded via .env).
 */
class Database
{
    private static ?PDO $instance = null;

    /**
     * Returns the shared PDO connection, creating it on first call.
     *
     * @throws RuntimeException If the connection cannot be established.
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $host    = $_ENV['DB_HOST']    ?? 'localhost';
            $port    = $_ENV['DB_PORT']    ?? '3306';
            $dbName  = $_ENV['DB_NAME']    ?? 'task_manager';
            $user    = $_ENV['DB_USER']    ?? '';
            $pass    = $_ENV['DB_PASS']    ?? '';
            $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";

            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_PERSISTENT         => false,
                ]);
            } catch (PDOException $e) {
                // Never expose raw DB errors to the client
                error_log('[DB] Connection failed: ' . $e->getMessage());
                throw new RuntimeException('Database connection unavailable.', 503);
            }
        }

        return self::$instance;
    }

    // Prevent direct instantiation / cloning
    private function __construct() {}
    private function __clone()    {}
}
