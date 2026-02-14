<?php

declare(strict_types=1);

namespace CFXP\Core\Session\Handlers;

use SessionHandlerInterface;

/**
 * Array-based session handler for testing.
 *
 * Stores session data in memory. Useful for unit tests
 * and situations where persistent sessions aren't needed.
 */
class ArraySessionHandler implements SessionHandlerInterface
{
    /**
     * The session data storage.
     *
     * @var array<string, string>
     */
    /** @var array<string, mixed> */

    protected array $storage = [];

    /**
     * The number of minutes the session should be valid.
     */
    protected int $minutes;

    /**
     * Create a new array session handler.
     *
     * @param int $minutes Session lifetime in minutes
     */
    public function __construct(int $minutes = 120)
    {
        $this->minutes = $minutes;
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
        return $this->storage[$id] ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $id, string $data): bool
    {
        $this->storage[$id] = $data;
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(string $id): bool
    {
        unset($this->storage[$id]);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc(int $max_lifetime): int|false
    {
        // No-op for array handler as sessions don't persist
        return 0;
    }

    /**
     * Check if a session exists.
     */
    public function exists(string $id): bool
    {
        return isset($this->storage[$id]);
    }

    /**
     * Clear all sessions.
     */
    public function flush(): void
    {
        $this->storage = [];
    }

    /**
     * Get all stored sessions (for testing).
     *
     * @return array<string, string>
     */
    /**
     * @return array<string, mixed>
     */
public function getStorage(): array
    {
        return $this->storage;
    }
}
