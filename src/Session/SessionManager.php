<?php

declare(strict_types=1);

namespace Denosys\Session;

use Denosys\Database\Connection\Connection;
use Denosys\Encryption\DecryptException;
use Denosys\Encryption\EncrypterInterface;
use Denosys\Session\Handlers\ArraySessionHandler;
use Denosys\Session\Handlers\CookieSessionHandler;
use Denosys\Session\Handlers\DatabaseSessionHandler;
use Denosys\Session\Handlers\FileSessionHandler;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use SessionHandlerInterface;

class SessionManager
{
    /**
     * Default session lifetime in minutes.
     */
    protected const DEFAULT_LIFETIME = 120;

    /**
     * The session store instance.
     */
    protected ?Store $session = null;

    /**
     * Custom driver creators.
     *
     * @var array<string, callable>
     */
    /** @var array<string, mixed> */

    protected array $customDrivers = [];

    /**
     * Create a new session manager.
     *
     * @param array<string, mixed> $config
     * @param EncrypterInterface|null $encrypter Optional encrypter for cookie encryption
     * @param Connection|null $connection Optional database connection for database driver
     * @param LoggerInterface|null $logger Optional logger for error reporting
     */
    public function __construct(
        /**
         * @param array<string, mixed> $config
         */
        protected array $config,
        protected ?EncrypterInterface $encrypter = null,
        protected ?Connection $connection = null,
        protected ?LoggerInterface $logger = null
    ) {}

    /**
     * Get the session store instance.
     */
    public function getSession(): Store
    {
        if (null !== $this->session) {
            return $this->session;
        }

        return $this->session = $this->buildSession();
    }

    /**
     * Build a new session store.
     */
    protected function buildSession(): Store
    {
        $handler = $this->createHandler();

        $store = new Store(
            $this->config['cookie'] ?? 'cfxp_session',
            $handler,
            $this->getSessionIdFromRequest()
        );

        if ($this->logger !== null) {
            $store->setLogger($this->logger);
        }

        if ($this->encrypter !== null) {
            $encrypt = (bool) ($this->config['encrypt'] ?? false);
            $store->setEncrypter($this->encrypter, $encrypt);
        }

        return $store;
    }

    /**
     * Create the session handler based on configuration.
     */
    protected function createHandler(): SessionHandlerInterface
    {
        $driver = $this->config['driver'] ?? 'file';

        if (isset($this->customDrivers[$driver])) {
            return ($this->customDrivers[$driver])($this->config);
        }

        return match ($driver) {
            'file' => $this->createFileHandler(),
            'database', 'db' => $this->createDatabaseHandler(),
            'cookie' => $this->createCookieHandler(),
            'array' => $this->createArrayHandler(),
            default => throw new InvalidArgumentException(
                "Unsupported session driver [{$driver}]"
            ),
        };
    }

    /**
     * Create a file session handler.
     */
    protected function createFileHandler(): FileSessionHandler
    {
        return new FileSessionHandler(
            $this->config['files'] ?? null,
            $this->config['lifetime'] ?? self::DEFAULT_LIFETIME
        );
    }

    /**
     * Create a database session handler.
     *
     * @throws InvalidArgumentException If Connection is not available
     */
    protected function createDatabaseHandler(): DatabaseSessionHandler
    {
        if (null === $this->connection) {
            throw new InvalidArgumentException(
                'The database session driver requires a database connection.'
            );
        }

        if ($this->shouldEncrypt() && null === $this->encrypter) {
            throw new InvalidArgumentException(
                'The database session driver requires an encrypter. ' .
                    'Ensure APP_KEY is set in your environment.'
            );
        }

        return new DatabaseSessionHandler(
            $this->connection,
            $this->config['table'] ?? 'sessions',
            $this->config['lifetime'] ?? self::DEFAULT_LIFETIME,
            $this->logger
        );
    }

    /**
     * Create a cookie session handler.
     *
     * @throws InvalidArgumentException If encrypter is not available
     */
    protected function createCookieHandler(): CookieSessionHandler
    {
        if ($this->shouldEncrypt() && null === $this->encrypter) {
            throw new InvalidArgumentException(
                'The cookie session driver requires an encrypter. ' .
                    'Ensure APP_KEY is set in your environment.'
            );
        }

        return new CookieSessionHandler(
            $this->encrypter,
            $this->config['lifetime'] ?? self::DEFAULT_LIFETIME,
            $this->config['cookie_data'] ?? 'cfxp_session_data',
            $this->getCookieConfig()
        );
    }

    /**
     * Create an array session handler.
     */
    protected function createArrayHandler(): ArraySessionHandler
    {
        return new ArraySessionHandler(
            $this->config['lifetime'] ?? self::DEFAULT_LIFETIME
        );
    }

    /**
     * Get session ID from request (cookie).
     */
    protected function getSessionIdFromRequest(): ?string
    {
        $cookieName = $this->config['cookie'] ?? 'cfxp_session';
        $cookieValue = $_COOKIE[$cookieName] ?? null;

        if (null === $cookieValue) {
            return null;
        }

        if ($this->shouldEncrypt() && null !== $this->encrypter) {
            try {
                return $this->encrypter->decrypt($cookieValue, false);
            } catch (DecryptException) {
                return null;
            }
        }

        return $cookieValue;
    }

    /**
     * Set the session cookie on the response.
     */
    public function setSessionCookie(Store $session): void
    {
        $config = $this->getCookieConfig();
        $sessionId = $session->getId();

        if ($this->shouldEncrypt() && null !== $this->encrypter) {
            $sessionId = $this->encrypter->encrypt($sessionId, false);
        }

        setcookie(
            $config['name'],
            $sessionId,
            [
                'expires' => $config['expire_on_close'] ? 0 : time() + ($config['lifetime'] * 60),
                'path' => $config['path'],
                'domain' => $config['domain'] ?? '',
                'secure' => $config['secure'],
                'httponly' => $config['http_only'],
                'samesite' => ucfirst($config['same_site']),
            ]
        );
    }

    /**
     * Determine if cookies should be encrypted.
     */
    protected function shouldEncrypt(): bool
    {
        return (bool) ($this->config['encrypt'] ?? false);
    }

    /**
     * Get cookie configuration.
     *
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
public function getCookieConfig(): array
    {
        return [
            'name' => $this->config['cookie'] ?? 'cfxp_session',
            'lifetime' => $this->config['lifetime'] ?? self::DEFAULT_LIFETIME,
            'expire_on_close' => $this->config['expire_on_close'] ?? false,
            'path' => $this->config['path'] ?? '/',
            'domain' => $this->config['domain'] ?? null,
            'secure' => $this->config['secure'] ?? false,
            'http_only' => $this->config['http_only'] ?? true,
            'same_site' => $this->config['same_site'] ?? 'lax',
        ];
    }

    /**
     * Determine if garbage collection should run.
     */
    public function shouldGarbageCollect(): bool
    {
        $lottery = $this->config['lottery'] ?? [2, 100];

        return random_int(1, $lottery[1]) <= $lottery[0];
    }

    /**
     * Run garbage collection on the session handler.
     */
    public function garbageCollect(): void
    {
        $handler = $this->getSession()->getHandler();
        $lifetime = ($this->config['lifetime'] ?? self::DEFAULT_LIFETIME) * 60;

        $handler->gc($lifetime);
    }

    /**
     * Register a custom driver creator.
     *
     * @param callable $callback Receives config array, returns SessionHandlerInterface
     */
    public function extend(string $driver, callable $callback): self
    {
        $this->customDrivers[$driver] = $callback;
        return $this;
    }

    /**
     * Get the encrypter instance.
     */
    public function getEncrypter(): ?EncrypterInterface
    {
        return $this->encrypter;
    }

    /**
     * Set the encrypter instance.
     */
    public function setEncrypter(EncrypterInterface $encrypter): self
    {
        $this->encrypter = $encrypter;
        return $this;
    }

    /**
     * Get the database connection.
     */
    public function getConnection(): ?Connection
    {
        return $this->connection;
    }

    /**
     * Set the database connection.
     */
    public function setConnection(Connection $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Get the configuration.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config['driver'] ?? 'file';
    }

    /**
     * Get the current driver name being used.
     */
    public function getDriver(): string
    {
        return $this->config['driver'] ?? 'file';
    }

    /**
     * Check if using database driver.
     */
    public function usingDatabase(): bool
    {
        return in_array($this->getDriver(), ['database', 'db'], true);
    }

    /**
     * Check if using file driver.
     */
    public function usingFile(): bool
    {
        return $this->getDriver() === 'file';
    }

    /**
     * Check if using cookie driver.
     */
    public function usingCookie(): bool
    {
        return $this->getDriver() === 'cookie';
    }
}
