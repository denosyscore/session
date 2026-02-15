<?php

declare(strict_types=1);

namespace Denosys\Session;

use Denosys\Container\ContainerInterface;
use Denosys\Database\Connection\Connection;
use Denosys\Encryption\EncrypterInterface;
use Denosys\Contracts\ServiceProviderInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class SessionServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(SessionManager::class, function (ContainerInterface $container) {
            $config = $this->getSessionConfig($container);
            $encrypter = $this->getEncrypter($container, $config);
            $connection = $this->getConnection($container, $config);
            $logger = $this->getLogger($container);

            return new SessionManager($config, $encrypter, $connection, $logger);
        });

        $container->alias('session.manager', SessionManager::class);

        $container->singleton(Store::class, function (ContainerInterface $container) {
            /** @var SessionManager $manager */
            $manager = $container->get(SessionManager::class);
            return $manager->getSession();
        });

        $container->singleton(SessionInterface::class, function (ContainerInterface $container) {
            return $container->get(Store::class);
        });

        $container->alias('session', SessionInterface::class);
    }

    public function boot(ContainerInterface $container, ?EventDispatcherInterface $dispatcher = null): void {}

    /**
     * @param array<string, mixed> $config
     */
    protected function getEncrypter(ContainerInterface $container, array $config): ?EncrypterInterface
    {
        $requiresEncryption = true === ($config['encrypt'] ?? false);

        try {
            return $container->get(EncrypterInterface::class);
        } catch (Throwable $e) {
            if ($requiresEncryption) {
                throw new RuntimeException(
                    'Session encryption requires APP_KEY to be set.',
                    0,
                    $e
                );
            }

            return null;
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function getConnection(ContainerInterface $container, array $config): ?Connection
    {
        $driver = $config['driver'] ?? 'file';
        $requiresDatabase = in_array($driver, ['database', 'db'], true);

        if (!$container->has(Connection::class)) {
            if ($requiresDatabase) {
                throw new RuntimeException(
                    'The database session driver requires a database connection. ' .
                        'Connection is not registered in container. Check database configuration.'
                );
            }

            return null;
        }

        try {
            return $container->get(Connection::class);
        } catch (Throwable $e) {
            if ($requiresDatabase) {
                throw new RuntimeException(
                    'The database session driver requires a database connection. ' .
                        'Ensure database is configured in config/database.php.',
                    0,
                    $e
                );
            }

            return null;
        }
    }

    protected function getLogger(ContainerInterface $container): ?LoggerInterface
    {
        try {
            return $container->get(LoggerInterface::class);
        } catch (Throwable) {
            return null;
        }
    }

    /**

     * @return array<string, mixed>

     */

protected function getSessionConfig(ContainerInterface $container): array

    {
        $configPath = $container->get('path.config') . '/session.php';

        if (!file_exists($configPath)) {
            return $this->getDefaultConfig($container);
        }

        $config = require $configPath;

        return is_array($config) ? $config : $this->getDefaultConfig($container);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultConfig(ContainerInterface $container): array
    {
        $basePath = $container->get('path.base');

        return [
            'driver' => 'file',
            'lifetime' => 120,
            'expire_on_close' => false,
            'encrypt' => false,
            'files' => $basePath . '/storage/framework/sessions',
            'table' => 'sessions',
            'cookie' => 'denosys_session',
            'cookie_data' => 'denosys_session_data',
            'path' => '/',
            'domain' => null,
            'secure' => false,
            'http_only' => true,
            'same_site' => 'lax',
            'lottery' => [2, 100],
        ];
    }
}
