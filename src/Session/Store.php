<?php

declare(strict_types=1);

namespace CFXP\Core\Session;

use CFXP\Core\Encryption\DecryptException;
use CFXP\Core\Encryption\EncrypterInterface;
use Psr\Log\LoggerInterface;
use SessionHandlerInterface;

class Store implements SessionInterface
{
    /**
     * The session attributes.
     *
     * @var array<string, mixed>
     */
    /** @var array<string, mixed> */

    protected array $attributes = [];

    /**
     * Whether the session has been started.
     */
    protected bool $started = false;

    /**
     * Flash data key in the session.
     */
    protected const FLASH_KEY = '__flash__';

    /**
     * Flash data keys that should be available for the next request.
     */
    protected const FLASH_NEW_KEY = '__flash_new__';

    /**
     * Flash data keys from the previous request.
     */
    protected const FLASH_OLD_KEY = '__flash_old__';

    /**
     * CSRF token key in the session.
     */
    protected const TOKEN_KEY = '__token__';

    /**
     * Previous URL key in the session.
     */
    protected const PREVIOUS_URL_KEY = '__previous_url__';

    /**
     * Optional logger for error reporting.
     */
    protected ?LoggerInterface $logger = null;

    /**
     * Optional encrypter for payload encryption.
     */
    protected ?EncrypterInterface $encrypter = null;

    /**
     * Whether to encrypt the session payload.
     */
    protected bool $encrypt = false;

    /**
     * Create a new session instance.
     */
    public function __construct(
        protected string $name,
        protected SessionHandlerInterface $handler,
        protected ?string $id = null,
    ) {
        $this->setId($this->id ?? $this->generateSessionId());
    }

    /**
     * Set the logger instance.
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Set the encrypter instance and enable encryption.
     */
    public function setEncrypter(EncrypterInterface $encrypter, bool $encrypt = true): void
    {
        $this->encrypter = $encrypter;
        $this->encrypt = $encrypt;
    }

    /**
     * Check if payload encryption is enabled.
     */
    public function isEncrypted(): bool
    {
        return $this->encrypt && $this->encrypter !== null;
    }

    /**
     * Start the session.
     */
    public function start(): bool
    {
        if ($this->started) {
            return true;
        }

        $this->loadSession();

        // Age flash data
        $this->ageFlashData();

        // Ensure token exists
        if (!$this->has(self::TOKEN_KEY)) {
            $this->regenerateToken();
        }

        $this->started = true;

        return true;
    }

    /**
     * Load the session data from the handler.
     */
    protected function loadSession(): void
    {
        $data = $this->handler->read($this->id);

        if ('' === $data) {
            return;
        }

        $decoded = $this->decodePayload($data);

        if ($decoded === null) {
            $this->logger?->warning('Failed to decode session payload, starting fresh session', [
                'session_id' => $this->id,
            ]);

            return;
        }

        $attributes = @unserialize($decoded);

        if (false !== $attributes && is_array($attributes)) {
            $this->attributes = $attributes;
        }
    }

    /**
     * Decode the session payload (base64 decode and optionally decrypt).
     */
    protected function decodePayload(string $data): ?string
    {
        $decoded = base64_decode($data, true);

        if ($decoded === false) {
            $this->logger?->warning('Invalid session payload: not valid base64', [
                'session_id' => $this->id,
            ]);

            return null;
        }

        if ($this->isEncrypted()) {
            try {
                return $this->encrypter->decrypt($decoded, false);
            } catch (DecryptException $e) {
                $this->logger?->warning('Failed to decrypt session payload', [
                    'session_id' => $this->id,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        }

        return $decoded;
    }

    /**
     * Save the session data to the handler.
     *
     * @return bool True if save was successful, false otherwise
     */
    public function save(): bool
    {
        $this->cleanFlashData();

        $data = $this->encodePayload(serialize($this->attributes));
        $result = $this->handler->write($this->id, $data);

        if (!$result) {
            $this->logger?->error('Session save failed', [
                'session_id' => $this->id,
                'session_name' => $this->name,
                'handler' => $this->handler::class,
                'data_size' => strlen($data),
                'encrypted' => $this->isEncrypted(),
            ]);
        }

        $this->started = false;

        return $result;
    }

    /**
     * Encode the session payload (optionally encrypt and base64 encode).
     */
    protected function encodePayload(string $data): string
    {
        if ($this->isEncrypted()) {
            $data = $this->encrypter->encrypt($data, false);
        }

        return base64_encode($data);
    }

    /**
     * Age the flash data for the session.
     */
    protected function ageFlashData(): void
    {
        $flashData = $this->get(self::FLASH_KEY, []);
        $oldKeys = $this->get(self::FLASH_OLD_KEY, []);
        $newKeys = $this->get(self::FLASH_NEW_KEY, []);

        foreach ($oldKeys as $key) {
            unset($flashData[$key]);
        }

        $this->put(self::FLASH_OLD_KEY, $newKeys);
        $this->put(self::FLASH_NEW_KEY, []);
        $this->put(self::FLASH_KEY, $flashData);
    }

    /**
     * Clean up flash data before saving.
     */
    protected function cleanFlashData(): void
    {
        $flashData = $this->get(self::FLASH_KEY, []);
        $oldKeys = $this->get(self::FLASH_OLD_KEY, []);

        foreach ($oldKeys as $key) {
            unset($flashData[$key]);
        }

        $this->put(self::FLASH_KEY, $flashData);
    }

    /**
     * Get the session ID.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set the session ID.
     */
    public function setId(string $id): void
    {
        $this->id = $this->isValidId($id) ? $id : $this->generateSessionId();
    }

    /**
     * Determine if the given session ID is valid.
     */
    protected function isValidId(string $id): bool
    {
        return 1 === preg_match('/^[a-zA-Z0-9]{40}$/', $id);
    }

    /**
     * Generate a new session ID.
     */
    protected function generateSessionId(): string
    {
        return bin2hex(random_bytes(20));
    }

    /**
     * Regenerate the session ID.
     */
    public function regenerate(bool $destroy = false): bool
    {
        if ($destroy) {
            $this->handler->destroy($this->id);
        }

        $this->setId($this->generateSessionId());

        return true;
    }

    /**
     * Invalidate and regenerate the session.
     */
    public function invalidate(): bool
    {
        $this->flush();
        return $this->regenerate(true);
    }

    /**
     * Get the session name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the session name.
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Check if the session has been started.
     */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * Get a value from the session.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Put a key/value pair in the session.
     */
    public function put(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return isset($this->attributes[$key]) && null !== $this->attributes[$key];
    }

    /**
     * @inheritDoc
     */
    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * @inheritDoc
     */
    /**
     * @return array<string, mixed>
     */
public function all(): array
    {
        return $this->attributes;
    }
    /**
     * @inheritDoc
     */
    public function forget(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * Remove multiple items from the session.
      * @param array<string> $keys
     */
    public function forgetMany(array $keys): void
    {
        foreach ($keys as $key) {
            $this->forget($key);
        }
    }

    /**
     * Remove all items from the session.
     */
    public function flush(): void
    {
        $this->attributes = [];
    }

    /**
     * Flash a key/value pair to the session.
     */
    public function flash(string $key, mixed $value): void
    {
        $flashData = $this->get(self::FLASH_KEY, []);
        $flashData[$key] = $value;
        $this->put(self::FLASH_KEY, $flashData);

        $newKeys = $this->get(self::FLASH_NEW_KEY, []);
        if (!in_array($key, $newKeys, true)) {
            $newKeys[] = $key;
        }
        $this->put(self::FLASH_NEW_KEY, $newKeys);

        // Remove from old keys if present
        $oldKeys = $this->get(self::FLASH_OLD_KEY, []);
        $this->put(self::FLASH_OLD_KEY, array_diff($oldKeys, [$key]));
    }

    /**
     * @inheritDoc
     */
    public function reflash(): void
    {
        $oldKeys = $this->get(self::FLASH_OLD_KEY, []);
        $newKeys = $this->get(self::FLASH_NEW_KEY, []);

        $this->put(self::FLASH_NEW_KEY, array_unique(array_merge($newKeys, $oldKeys)));
        $this->put(self::FLASH_OLD_KEY, []);
    }

    /**
     * @inheritDoc
     */
    public function keep(array|string $keys): void
    {
        $keys = is_array($keys) ? $keys : [$keys];
        $oldKeys = $this->get(self::FLASH_OLD_KEY, []);
        $newKeys = $this->get(self::FLASH_NEW_KEY, []);

        $this->put(self::FLASH_NEW_KEY, array_unique(array_merge($newKeys, $keys)));
        $this->put(self::FLASH_OLD_KEY, array_diff($oldKeys, $keys));
    }

    /**
     * @inheritDoc
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $flashData = $this->get(self::FLASH_KEY, []);
        return $flashData[$key] ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function hasFlash(string $key): bool
    {
        $flashData = $this->get(self::FLASH_KEY, []);
        return isset($flashData[$key]);
    }

    /**
     * @inheritDoc
      * @return array<string, mixed>
     */
    public function getFlashAll(): array
    {
        return $this->get(self::FLASH_KEY, []);
    }

    /**
     * @inheritDoc
     */
    public function previousUrl(): ?string
    {
        return $this->get(self::PREVIOUS_URL_KEY);
    }

    /**
     * @inheritDoc
     */
    public function setPreviousUrl(string $url): void
    {
        $this->put(self::PREVIOUS_URL_KEY, $url);
    }

    /**
     * @inheritDoc
     */
    public function token(): string
    {
        return $this->get(self::TOKEN_KEY, '');
    }

    /**
     * @inheritDoc
     */
    public function regenerateToken(): string
    {
        $token = bin2hex(random_bytes(20));
        $this->put(self::TOKEN_KEY, $token);
        return $token;
    }

    /**
     * Push a value onto a session array.
     */
    public function push(string $key, mixed $value): void
    {
        $array = $this->get($key, []);

        if (!is_array($array)) {
            $array = [$array];
        }

        $array[] = $value;
        $this->put($key, $array);
    }

    /**
     * Increment a session value.
     */
    public function increment(string $key, int $amount = 1): int
    {
        $value = (int) $this->get($key, 0) + $amount;
        $this->put($key, $value);
        return $value;
    }

    /**
     * Decrement a session value.
     */
    public function decrement(string $key, int $amount = 1): int
    {
        return $this->increment($key, -$amount);
    }

    /**
     * Get the value of a given key and then forget it.
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    /**
     * Get the session handler instance.
     */
    public function getHandler(): SessionHandlerInterface
    {
        return $this->handler;
    }

    /**
     * Set the user ID for the current session in the database.
     *
     * This updates the user_id column in the sessions table (when using database driver)
     * to enable features like "logout everywhere" and "view active sessions".
     */
    public function setUserId(?int $userId): bool
    {
        // Check if the handler supports setUserId
        if (method_exists($this->handler, 'setUserId')) {
            return $this->handler->setUserId($this->id, $userId);
        }

        return true; // No-op for handlers that don't support it
    }
}
