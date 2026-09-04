<?php

declare(strict_types=1);

namespace Chubbyphp\RateLimit\ServiceFactory;

use Chubbyphp\Laminas\Config\Factory\AbstractFactory;
use Chubbyphp\RateLimit\AttributeKeyResolver;
use Chubbyphp\RateLimit\HeaderKeyResolver;
use Chubbyphp\RateLimit\KeyResolver;
use Chubbyphp\RateLimit\KeyResolverInterface;
use Chubbyphp\RateLimit\StaticKeyResolver;
use Psr\Container\ContainerInterface;

/**
 * Reads `config.chubbyphp.rateLimit.keys` (or `config.chubbyphp.rateLimit.<name>.keys` for named factories): the key
 * resolvers in order (the first resolved key wins), the last one should resolve for every request. Each entry is one
 * of:
 *  - `['header' => 'X-Api-Key']`: a header name, authenticated by a middleware in front (not `X-Forwarded-For`, see
 *    HeaderKeyResolver)
 *  - `['attribute' => 'clientIp']`: an attribute name, e.g. `clientIp` set by chubbyphp/chubbyphp-trusted-proxy
 *  - `['static' => 'global']`: a fixed key for every request, as the last entry so that requests without key share
 *    one limit.
 */
final class KeyResolverFactory extends AbstractFactory
{
    /**
     * @var array<string, class-string<KeyResolverInterface>>
     */
    private const array KEY_RESOLVER_CLASSES = [
        'header' => HeaderKeyResolver::class,
        'attribute' => AttributeKeyResolver::class,
        'static' => StaticKeyResolver::class,
    ];

    public function __invoke(ContainerInterface $container): KeyResolverInterface
    {
        /** @var array{chubbyphp?: array{rateLimit?: array<string, mixed>}} $config */
        $config = $container->get('config');

        /** @var array{keys?: mixed} $rateLimitConfig */
        $rateLimitConfig = $this->resolveConfig($config['chubbyphp']['rateLimit'] ?? []);

        $path = 'config.chubbyphp.rateLimit'.('' === $this->name ? '' : '.'.$this->name).'.keys';

        $keys = $rateLimitConfig['keys'] ?? null;

        // without any key the middleware would fail for every request
        if (!\is_array($keys) || [] === $keys) {
            throw new \InvalidArgumentException(\sprintf(
                '%s must be a non empty array of key resolvers, %s given',
                $path,
                [] === $keys ? 'empty array' : get_debug_type($keys)
            ));
        }

        $keyResolvers = [];

        foreach ($keys as $index => $key) {
            $keyResolvers[] = self::createKeyResolver($key, \sprintf('%s[%s]', $path, $index));
        }

        return new KeyResolver(...$keyResolvers);
    }

    private static function createKeyResolver(mixed $key, string $path): KeyResolverInterface
    {
        if (!\is_array($key) || 1 !== \count($key)) {
            throw self::invalidKeyResolver($key, $path);
        }

        /** @var int|string $type */
        $type = array_key_first($key);

        // an unknown type (e.g. a typo) would otherwise silently disable the rate limiting
        $class = self::KEY_RESOLVER_CLASSES[$type] ?? throw self::invalidKeyResolver($key, $path);

        $name = $key[$type];

        // an empty name (or static key) would never (header, attribute) or always (static, as the empty key) resolve
        if (!\is_string($name) || '' === $name) {
            throw new \InvalidArgumentException(\sprintf(
                '%s.%s must be a non empty string, %s given',
                $path,
                $type,
                \is_string($name) ? '""' : get_debug_type($name)
            ));
        }

        return new $class($name);
    }

    private static function invalidKeyResolver(mixed $key, string $path): \InvalidArgumentException
    {
        return new \InvalidArgumentException(\sprintf(
            '%s must be an array with exactly one of the keys %s, %s given',
            $path,
            implode(', ', array_keys(self::KEY_RESOLVER_CLASSES)),
            \is_array($key) ? json_encode($key, JSON_THROW_ON_ERROR) : get_debug_type($key)
        ));
    }
}
