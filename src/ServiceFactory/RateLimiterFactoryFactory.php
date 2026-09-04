<?php

declare(strict_types=1);

namespace Chubbyphp\RateLimit\ServiceFactory;

use Chubbyphp\Laminas\Config\Factory\AbstractFactory;
use Psr\Container\ContainerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * Creates the symfony/rate-limiter `RateLimiterFactory` out of `config.chubbyphp.rateLimit` (or
 * `config.chubbyphp.rateLimit.<name>` for named factories): every key except `keys` (see KeyResolverFactory) is an
 * option of the `RateLimiterFactory` (`policy`, `limit`, `interval`, `rate`, ...), `id` (the prefix of the keys
 * within the storage) defaults to `chubbyphp.rateLimit` (or `chubbyphp.rateLimit.<name>`, so that named limiters
 * sharing one storage do not share their counters).
 *
 * The storage is the service `StorageInterface::class.<name>` of the container, `StorageInterface::class` (shared by
 * all names) otherwise, and an `InMemoryStorage` (counting within a single process only, register a `CacheStorage`
 * for a shared limit) if none is registered. The lock factory gets resolved the same way (`LockFactory::class`), none
 * by default (concurrent requests of one key may then exceed the limit by a few, see the README).
 */
final class RateLimiterFactoryFactory extends AbstractFactory
{
    private const string DEFAULT_ID = 'chubbyphp.rateLimit';

    public function __invoke(ContainerInterface $container): RateLimiterFactoryInterface
    {
        /** @var array{chubbyphp?: array{rateLimit?: array<string, mixed>}} $config */
        $config = $container->get('config');

        $rateLimitConfig = $this->resolveConfig($config['chubbyphp']['rateLimit'] ?? []);

        $path = 'config.chubbyphp.rateLimit'.('' === $this->name ? '' : '.'.$this->name);

        unset($rateLimitConfig['keys']);

        $rateLimitConfig['id'] ??= self::DEFAULT_ID.('' === $this->name ? '' : '.'.$this->name);

        /** @var null|StorageInterface $storage */
        $storage = $this->resolveSharedDependency($container, StorageInterface::class);

        /** @var null|LockFactory $lockFactory */
        $lockFactory = $this->resolveSharedDependency($container, LockFactory::class);

        try {
            return new RateLimiterFactory($rateLimitConfig, $storage ?? new InMemoryStorage(), $lockFactory);
        } catch (\InvalidArgumentException $e) {
            // the options resolver messages name the option (`The required option "policy" is missing.`)
            throw new \InvalidArgumentException($path.': '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * The named service if registered, the unnamed one (shared by all names) otherwise, null if neither.
     */
    private function resolveSharedDependency(ContainerInterface $container, string $class): ?object
    {
        foreach (array_unique([$class.$this->name, $class]) as $id) {
            if ($container->has($id)) {
                /** @var object */
                return $container->get($id);
            }
        }

        return null;
    }
}
