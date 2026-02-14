<?php

declare(strict_types=1);

namespace Denosys\Session\Handlers;

use Denosys\Database\Connection\Connection;
use Denosys\Database\Exceptions\DatabaseException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SessionHandlerInterface;

/**
 * Database session handler.
 *
 * Stores session data in a database table. This is ideal for
 * load-balanced environments where sessions need to be shared
 * across multiple servers.
 *
 * Required table schema:
 * ```sql
 * CREATE TABLE sessions (
 *     id VARCHAR(128) NOT NULL PRIMARY KEY,
 *     payload TEXT NOT NULL,
 *     last_activity INT UNSIGNED NOT NULL,
 *     user_id INT UNSIGNED NULL,
 *     ip_address VARCHAR(45) NULL,
 *     user_agent VARCHAR(255) NULL,
 *     INDEX sessions_last_activity_index (last_activity)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 * ```
 */
class DatabaseSessionHandler implements SessionHandlerInterface
{
    /**
     * The database connection.
     */
    protected Connection $connection;

    /**
     * The name of the session table.
     */
    protected string $table;

    /**
     * The number of minutes the session should be valid.
     */
    protected int $minutes;

    /**
     * The logger instance.
     */
    protected LoggerInterface $logger;

    /**
     * The user ID to associate with the session.
     */
    protected ?int $userId = null;

    /**
     * Create a new database session handler.
     *
     * @param Connection $connection Database connection
     * @param string $table Session table name
     * @param int $minutes Session lifetime in minutes
     * @param LoggerInterface|null $logger Optional logger for error reporting
     */
    public function __construct(
        Connection $connection,
        string $table = 'sessions',
        int $minutes = 120,
        ?LoggerInterface $logger = null
    ) {
        $this->connection = $connection;
        $this->table = $table;
        $this->minutes = $minutes;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function open(string $path, string $name): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $id): string|false
    {
        try {
            $session = $this->connection->table($this->table)
                ->select(['payload'])
                ->where('id', '=', $id)
                ->where('last_activity', '>=', $this->getExpiryTime())
                ->first();

            if ($session && isset($session->payload)) {
                return $session->payload;
            }

            return '';
        } catch (DatabaseException $e) {
            $this->logger->error('Failed to read session from database', [
                'session_id' => $id,
                'table' => $this->table,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $id, string $data): bool
    {
        try {
            // Build the values to upsert
            $values = [
                'id' => $id,
                'payload' => $data,
                'last_activity' => time(),
                'ip_address' => $this->getIpAddress(),
                'user_agent' => $this->getUserAgent(),
            ];

            // Include user_id if set
            if ($this->userId !== null) {
                $values['user_id'] = $this->userId;
            }

            // Columns to update on conflict (user_id is included when set)
            $updateColumns = ['payload', 'last_activity', 'ip_address', 'user_agent'];
            if ($this->userId !== null) {
                $updateColumns[] = 'user_id';
            }

            return $this->connection->table($this->table)->upsert(
                $values,
                ['id'],
                $updateColumns
            );
        } catch (DatabaseException $e) {
            $this->logger->error('Failed to write session to database', [
                'session_id' => $id,
                'table' => $this->table,
                'error' => $e->getMessage(),
                'hint' => $this->getErrorHint($e),
            ]);

            return false;
        }
    }

    /**
     * Get a helpful hint based on the database exception.
     */
    protected function getErrorHint(DatabaseException $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, "doesn't exist") || str_contains($message, 'no such table')) {
            return "The '{$this->table}' table does not exist. Run the migration or create it manually.";
        }

        if (str_contains($message, 'Unknown column')) {
            return "The '{$this->table}' table is missing required columns. Check the schema.";
        }

        if (str_contains($message, 'Access denied')) {
            return 'Database access denied. Check your database credentials.';
        }

        return 'Check database connection and table schema.';
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(string $id): bool
    {
        try {
            $this->connection->table($this->table)
                ->where('id', '=', $id)
                ->delete();

            return true;
        } catch (DatabaseException $e) {
            $this->logger->error('Failed to destroy session in database', [
                'session_id' => $id,
                'table' => $this->table,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function gc(int $max_lifetime): int|false
    {
        try {
            $expiry = time() - $max_lifetime;

            return $this->connection->table($this->table)
                ->where('last_activity', '<', $expiry)
                ->delete();
        } catch (DatabaseException $e) {
            $this->logger->warning('Failed to garbage collect sessions', [
                'table' => $this->table,
                'max_lifetime' => $max_lifetime,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get the expiry timestamp.
     */
    protected function getExpiryTime(): int
    {
        return time() - ($this->minutes * 60);
    }

    /**
     * Get the client IP address.
     */
    protected function getIpAddress(): ?string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_CLIENT_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? null;
    }

    /**
     * Get the user agent.
     */
    protected function getUserAgent(): ?string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Truncate to fit in column
        if ($userAgent !== null && strlen($userAgent) > 255) {
            $userAgent = substr($userAgent, 0, 255);
        }

        return $userAgent;
    }

    /**
     * Set the user ID for the current session.
     *
     * This stores the user ID to be included in the next write() operation.
     * The actual database update happens when the session is saved.
     */
    public function setUserId(string $sessionId, ?int $userId): bool
    {
        $this->userId = $userId;
        return true;
    }

    /**
     * Get all sessions for a specific user.
     *
     * @return array<object>
     */
    /**
     * @return array<string, mixed>
     */
public function getSessionsByUser(int $userId): array
    {
        try {
            return $this->connection->table($this->table)
                ->select(['id', 'last_activity', 'ip_address', 'user_agent'])
                ->where('user_id', '=', $userId)
                ->where('last_activity', '>=', $this->getExpiryTime())
                ->orderByDesc('last_activity')
                ->get();
        } catch (DatabaseException $e) {
            $this->logger->error('Failed to get sessions for user', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Destroy all sessions for a specific user (logout everywhere).
     */
    public function destroyUserSessions(int $userId, ?string $exceptSessionId = null): bool
    {
        try {
            $query = $this->connection->table($this->table)
                ->where('user_id', '=', $userId);

            if ($exceptSessionId !== null) {
                $query->where('id', '!=', $exceptSessionId);
            }

            $query->delete();

            return true;
        } catch (DatabaseException $e) {
            $this->logger->error('Failed to destroy user sessions', [
                'user_id' => $userId,
                'except_session' => $exceptSessionId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get the table name.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get the database connection.
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }
}
