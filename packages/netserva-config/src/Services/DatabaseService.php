<?php

namespace NetServa\Config\Services;

use Illuminate\Support\Facades\Process;
use NetServa\Config\Models\Database;
use NetServa\Config\Models\DatabaseConnection;
use NetServa\Config\Models\DatabaseCredential;

class DatabaseService
{
    /**
     * List all database connections
     */
    public function listConnections(bool $activeOnly = false): \Illuminate\Database\Eloquent\Collection
    {
        $query = DatabaseConnection::with('databases');

        if ($activeOnly) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Test a database connection
     */
    public function testConnection(DatabaseConnection $connection): bool
    {
        return $connection->testConnection();
    }

    /**
     * Create a new database
     */
    public function createDatabase(DatabaseConnection $connection, string $name, array $options = []): Database
    {
        if (empty(trim($name))) {
            throw new \InvalidArgumentException('Database name cannot be empty');
        }

        $database = Database::create([
            'connection_id' => $connection->id,
            'name' => $name,
            'charset' => $options['charset'] ?? 'utf8mb4',
            'collation' => $options['collation'] ?? 'utf8mb4_unicode_ci',
        ]);

        // Execute SQL command to create database
        $this->executeCreateDatabaseCommand($connection, $name, $options);

        return $database;
    }

    /**
     * Drop a database
     */
    public function dropDatabase(DatabaseConnection $connection, string $name): bool
    {
        // Check if database exists first
        $database = Database::where('connection_id', $connection->id)
            ->where('name', $name)
            ->first();

        if (! $database) {
            return false;
        }

        // Execute SQL command to drop database
        $this->executeDropDatabaseCommand($connection, $name);

        // Remove from our records
        $database->delete();

        return true;
    }

    /**
     * Create a database user
     */
    public function createUser(Database $database, string $username, string $password): DatabaseCredential
    {
        if (empty(trim($username))) {
            throw new \InvalidArgumentException('Username cannot be empty');
        }

        if (empty(trim($password))) {
            throw new \InvalidArgumentException('Password cannot be empty');
        }

        $credential = DatabaseCredential::create([
            'database_id' => $database->id,
            'username' => $username,
            'password' => $password, // Will be encrypted by model mutator
        ]);

        // Execute SQL command to create user
        $this->executeCreateUserCommand($database->connection, $username, $password);

        return $credential;
    }

    /**
     * Get connection statistics
     */
    public function getConnectionStats(DatabaseConnection $connection): array
    {
        $databases = $connection->databases;

        return [
            'database_count' => $databases->count(),
            'active_databases' => $databases->where('is_active', true)->count(),
        ];
    }

    /**
     * Execute create database command
     */
    private function executeCreateDatabaseCommand(DatabaseConnection $connection, string $name, array $options): void
    {
        $charset = $options['charset'] ?? 'utf8mb4';
        $collation = $options['collation'] ?? 'utf8mb4_unicode_ci';

        $command = match ($connection->engine) {
            'mysql' => sprintf(
                "mysql -h %s -P %d -u %s -p'%s' -e \"CREATE DATABASE %s CHARACTER SET %s COLLATE %s\"",
                $connection->host,
                $connection->port,
                $connection->username,
                $connection->password,
                $name,
                $charset,
                $collation
            ),
            'postgresql' => sprintf(
                'psql -h %s -p %d -U %s -c "CREATE DATABASE %s"',
                $connection->host,
                $connection->port,
                $connection->username,
                $name
            ),
            default => sprintf(
                "mysql -h %s -P %d -u %s -p'%s' -e \"CREATE DATABASE %s\"",
                $connection->host,
                $connection->port,
                $connection->username,
                $connection->password,
                $name
            )
        };

        Process::run($command);
    }

    /**
     * Execute drop database command
     */
    private function executeDropDatabaseCommand(DatabaseConnection $connection, string $name): void
    {
        $command = match ($connection->engine) {
            'mysql' => sprintf(
                "mysql -h %s -P %d -u %s -p'%s' -e \"DROP DATABASE %s\"",
                $connection->host,
                $connection->port,
                $connection->username,
                $connection->password,
                $name
            ),
            'postgresql' => sprintf(
                'psql -h %s -p %d -U %s -c "DROP DATABASE %s"',
                $connection->host,
                $connection->port,
                $connection->username,
                $name
            ),
            default => sprintf(
                "mysql -h %s -P %d -u %s -p'%s' -e \"DROP DATABASE %s\"",
                $connection->host,
                $connection->port,
                $connection->username,
                $connection->password,
                $name
            )
        };

        Process::run($command);
    }

    /**
     * Execute create user command
     */
    private function executeCreateUserCommand(DatabaseConnection $connection, string $username, string $password): void
    {
        $command = match ($connection->engine) {
            'mysql' => sprintf(
                "mysql -h %s -P %d -u %s -p'%s' -e \"CREATE USER '%s'@'localhost' IDENTIFIED BY '%s'\"",
                $connection->host,
                $connection->port,
                $connection->username,
                $connection->password,
                $username,
                $password
            ),
            'postgresql' => sprintf(
                "psql -h %s -p %d -U %s -c \"CREATE USER %s PASSWORD '%s'\"",
                $connection->host,
                $connection->port,
                $connection->username,
                $username,
                $password
            ),
            default => sprintf(
                "mysql -h %s -P %d -u %s -p'%s' -e \"CREATE USER '%s'@'localhost' IDENTIFIED BY '%s'\"",
                $connection->host,
                $connection->port,
                $connection->username,
                $connection->password,
                $username,
                $password
            )
        };

        Process::run($command);
    }
}
