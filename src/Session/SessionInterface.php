<?php

declare(strict_types=1);

namespace CFXP\Core\Session;

interface SessionInterface
{
    /**
     * Start the session.
     */
    public function start(): bool;

    /**
     * Save the session data.
     *
     * @return bool True if save was successful, false otherwise
     */
    public function save(): bool;

    /**
     * Get the session ID.
     */
    public function getId(): string;

    /**
     * Set the session ID.
     */
    public function setId(string $id): void;

    /**
     * Regenerate the session ID.
     *
     * @param bool $destroy Whether to delete the old session
     */
    public function regenerate(bool $destroy = false): bool;

    /**
     * Invalidate and regenerate the session.
     */
    public function invalidate(): bool;

    /**
     * Get the session name.
     */
    public function getName(): string;

    /**
     * Set the session name.
     */
    public function setName(string $name): void;

    /**
     * Check if the session has been started.
     */
    public function isStarted(): bool;

    /**
     * Get a value from the session.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Put a key/value pair in the session.
     */
    public function put(string $key, mixed $value): void;

    /**
     * Check if a key exists in the session and is not null.
     */
    public function has(string $key): bool;

    /**
     * Check if a key is present in the session even if it is null.
     */
    public function exists(string $key): bool;

    /**
     * Get all session data.
      * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Remove an item from the session.
     */
    public function forget(string $key): void;

    /**
     * Remove multiple items from the session.
     *
     * @param array<string> $keys
     */
    public function forgetMany(array $keys): void;

    /**
     * Remove all items from the session.
     */
    public function flush(): void;

    /**
     * Flash a key/value pair to the session.
     * Flash data is only available for the next request.
     */
    public function flash(string $key, mixed $value): void;

    /**
     * Re-flash all of the session flash data.
     */
    public function reflash(): void;

    /**
     * Re-flash a subset of the current flash data.
     *
     * @param array<string>|string $keys
     */
    public function keep(array|string $keys): void;

    /**
     * Get and delete a flash value from the session.
     */
    public function getFlash(string $key, mixed $default = null): mixed;

    /**
     * Check if a flash key exists.
     */
    public function hasFlash(string $key): bool;

    /**
     * Get all flash data.
      * @return array<string, mixed>
     */
    public function getFlashAll(): array;

    /**
     * Get the previous URL from the session.
     */
    public function previousUrl(): ?string;

    /**
     * Set the previous URL in the session.
     */
    public function setPreviousUrl(string $url): void;

    /**
     * Get a token for CSRF protection.
     */
    public function token(): string;

    /**
     * Regenerate the CSRF token.
     */
    public function regenerateToken(): string;

    /**
     * Push a value onto a session array.
     */
    public function push(string $key, mixed $value): void;

    /**
     * Increment a session value.
     */
    public function increment(string $key, int $amount = 1): int;

    /**
     * Decrement a session value.
     */
    public function decrement(string $key, int $amount = 1): int;

    /**
     * Get the value of a given key and then forget it.
     */
    public function pull(string $key, mixed $default = null): mixed;

    /**
     * Set the user ID for the current session.
     *
     * This updates the user_id column in the sessions table when using the database driver.
     * Enables features like "logout from all devices" and "view active sessions".
     */
    public function setUserId(?int $userId): bool;
}
