<?php

declare(strict_types=1);

namespace CFXP\Core\Session\Handlers;

use SessionHandlerInterface;
use RuntimeException;

/**
 * File-based session handler.
 *
 * Stores session data in files on the filesystem.
 * Implements PHP's SessionHandlerInterface for compatibility.
 */
class FileSessionHandler implements SessionHandlerInterface
{
    /**
     * The path where sessions should be stored.
     */
    protected string $path;

    /**
     * The number of minutes the session should be valid.
     */
    protected int $minutes;

    /**
     * Create a new file-based session handler.
     *
     * @param string $path Directory path for session files
     * @param int $minutes Session lifetime in minutes
     */
    public function __construct(string $path, int $minutes)
    {
        $this->path = rtrim($path, '/');
        $this->minutes = $minutes;

        $this->ensureSessionDirectoryExists();
    }

    /**
     * Ensure the session directory exists.
     *
     * @throws RuntimeException
     */
    protected function ensureSessionDirectoryExists(): void
    {
        if (!is_dir($this->path)) {
            if (!@mkdir($this->path, 0755, true) && !is_dir($this->path)) {
                throw new RuntimeException(
                    sprintf('Session directory "%s" could not be created.', $this->path)
                );
            }
        }

        if (!is_writable($this->path)) {
            throw new RuntimeException(
                sprintf('Session directory "%s" is not writable.', $this->path)
            );
        }
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
        $path = $this->getSessionPath($id);

        if (file_exists($path) && filemtime($path) >= $this->getExpirationTime()) {
            $content = file_get_contents($path);
            return $content !== false ? $content : '';
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $id, string $data): bool
    {
        $path = $this->getSessionPath($id);

        $result = file_put_contents($path, $data, LOCK_EX);

        if ($result !== false) {
            // Update the file modification time
            touch($path);
        }

        return $result !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(string $id): bool
    {
        $path = $this->getSessionPath($id);

        if (file_exists($path)) {
            return unlink($path);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param int $max_lifetime Max lifetime in seconds
     */
    public function gc(int $max_lifetime): int|false
    {
        $deleted = 0;
        $files = glob($this->path . '/sess_*');

        if ($files === false) {
            return false;
        }

        $expirationTime = time() - $max_lifetime;

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $expirationTime) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Get the full path for the given session ID.
     */
    protected function getSessionPath(string $id): string
    {
        return $this->path . '/sess_' . $id;
    }

    /**
     * Get the expiration timestamp.
     */
    protected function getExpirationTime(): int
    {
        return time() - ($this->minutes * 60);
    }

    /**
     * Determine if garbage collection should run.
     *
     * @param array{0: int, 1: int} $lottery [chance, outOf]
      * @param array<string, mixed> $lottery
     */
    public function shouldGarbageCollect(array $lottery): bool
    {
        return random_int(1, $lottery[1]) <= $lottery[0];
    }

    /**
     * Get the path where sessions are stored.
     */
    public function getPath(): string
    {
        return $this->path;
    }
}
