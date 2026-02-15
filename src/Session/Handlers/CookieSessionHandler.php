<?php

declare(strict_types=1);

namespace Denosys\Session\Handlers;

use Denosys\Encryption\DecryptException;
use Denosys\Encryption\EncrypterInterface;
use SessionHandlerInterface;

/**
 * Cookie-based session handler.
 *
 * Stores session data in encrypted cookies. Useful for stateless
 * deployments where server-side session storage isn't available.
 *
 * Note: Cookie size is limited (~4KB), so this handler is best
 * suited for small session payloads.
 */
class CookieSessionHandler implements SessionHandlerInterface
{
    /**
     * Maximum cookie size in bytes (leaving room for encryption overhead).
     */
    protected const MAX_COOKIE_SIZE = 3500;

    /**
     * The encrypter instance.
     */
    protected EncrypterInterface $encrypter;

    /**
     * The number of minutes the session should be valid.
     */
    protected int $minutes;

    /**
     * The cookie name for session data.
     */
    protected string $cookieName;

    /**
     * Cookie configuration options.
     *
     * @var array<string, mixed>
     */
    protected array $cookieConfig;

    /**
     * Session data pending write.
     */
    protected ?string $pendingData = null;

    /**
     * Create a new cookie session handler.
     *
     * @param EncrypterInterface $encrypter
     * @param int $minutes Session lifetime in minutes
     * @param string $cookieName Cookie name for session data
     * @param array<string, mixed> $cookieConfig Cookie configuration
     */
    public function __construct(
        EncrypterInterface $encrypter,
        int $minutes = 120,
        string $cookieName = 'denosys_session_data',
        array $cookieConfig = []
    ) {
        $this->encrypter = $encrypter;
        $this->minutes = $minutes;
        $this->cookieName = $cookieName;
        $this->cookieConfig = array_merge([
            'path' => '/',
            'domain' => null,
            'secure' => false,
            'http_only' => true,
            'same_site' => 'lax',
            'expire_on_close' => false,
        ], $cookieConfig);
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
        // Write pending data to cookie
        if ($this->pendingData !== null) {
            $this->setCookie($this->pendingData);
            $this->pendingData = null;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $id): string|false
    {
        $cookieValue = $_COOKIE[$this->cookieName] ?? null;

        if ($cookieValue === null) {
            return '';
        }

        try {
            $decrypted = $this->encrypter->decrypt($cookieValue, false);

            // Validate the decrypted data structure
            $data = json_decode($decrypted, true);

            if (!is_array($data) || !isset($data['id'], $data['data'], $data['expires'])) {
                return '';
            }

            // Check if session ID matches and hasn't expired
            if ($data['id'] !== $id || $data['expires'] < time()) {
                return '';
            }

            return $data['data'];
        } catch (DecryptException) {
            // Cookie was tampered with or invalid
            return '';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $id, string $data): bool
    {
        $payload = json_encode([
            'id' => $id,
            'data' => $data,
            'expires' => time() + ($this->minutes * 60),
        ], JSON_THROW_ON_ERROR);

        $encrypted = $this->encrypter->encrypt($payload, false);

        // Check size limit
        if (strlen($encrypted) > self::MAX_COOKIE_SIZE) {
            trigger_error(
                'Session data exceeds cookie size limit. Consider using file or database sessions.',
                E_USER_WARNING
            );
            return false;
        }

        // Store for writing in close()
        $this->pendingData = $encrypted;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(string $id): bool
    {
        // Clear the cookie
        $this->clearCookie();
        $this->pendingData = null;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc(int $max_lifetime): int|false
    {
        // No server-side cleanup needed for cookie sessions
        return 0;
    }

    /**
     * Set the session data cookie.
     */
    protected function setCookie(string $value): void
    {
        $expires = $this->cookieConfig['expire_on_close']
            ? 0
            : time() + ($this->minutes * 60);

        setcookie(
            $this->cookieName,
            $value,
            [
                'expires' => $expires,
                'path' => $this->cookieConfig['path'],
                'domain' => $this->cookieConfig['domain'] ?? '',
                'secure' => $this->cookieConfig['secure'],
                'httponly' => $this->cookieConfig['http_only'],
                'samesite' => ucfirst($this->cookieConfig['same_site']),
            ]
        );
    }

    /**
     * Clear the session data cookie.
     */
    protected function clearCookie(): void
    {
        setcookie(
            $this->cookieName,
            '',
            [
                'expires' => time() - 3600,
                'path' => $this->cookieConfig['path'],
                'domain' => $this->cookieConfig['domain'] ?? '',
                'secure' => $this->cookieConfig['secure'],
                'httponly' => $this->cookieConfig['http_only'],
                'samesite' => ucfirst($this->cookieConfig['same_site']),
            ]
        );

        unset($_COOKIE[$this->cookieName]);
    }

    /**
     * Get the cookie name.
     */
    public function getCookieName(): string
    {
        return $this->cookieName;
    }
}
