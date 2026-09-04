# chubbyphp-rate-limit

[![CI](https://github.com/chubbyphp/chubbyphp-rate-limit/actions/workflows/ci.yml/badge.svg)](https://github.com/chubbyphp/chubbyphp-rate-limit/actions/workflows/ci.yml)
[![Coverage Status](https://coveralls.io/repos/github/chubbyphp/chubbyphp-rate-limit/badge.svg?branch=master)](https://coveralls.io/github/chubbyphp/chubbyphp-rate-limit?branch=master)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fchubbyphp%2Fchubbyphp-rate-limit%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/chubbyphp/chubbyphp-rate-limit/master)
[![Latest Stable Version](https://poser.pugx.org/chubbyphp/chubbyphp-rate-limit/v)](https://packagist.org/packages/chubbyphp/chubbyphp-rate-limit)
[![Total Downloads](https://poser.pugx.org/chubbyphp/chubbyphp-rate-limit/downloads)](https://packagist.org/packages/chubbyphp/chubbyphp-rate-limit)
[![Monthly Downloads](https://poser.pugx.org/chubbyphp/chubbyphp-rate-limit/d/monthly)](https://packagist.org/packages/chubbyphp/chubbyphp-rate-limit)

[![bugs](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-rate-limit&metric=bugs)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-rate-limit)
[![code_smells](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-rate-limit&metric=code_smells)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-rate-limit)
[![coverage](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-rate-limit&metric=coverage)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-rate-limit)
[![duplicated_lines_density](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-rate-limit&metric=duplicated_lines_density)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-rate-limit)
[![ncloc](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-rate-limit&metric=ncloc)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-rate-limit)
[![sqale_rating](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-rate-limit&metric=sqale_rating)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-rate-limit)
[![alert_status](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-rate-limit&metric=alert_status)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-rate-limit)
[![reliability_rating](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-rate-limit&metric=reliability_rating)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-rate-limit)
[![security_rating](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-rate-limit&metric=security_rating)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-rate-limit)
[![sqale_index](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-rate-limit&metric=sqale_index)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-rate-limit)
[![vulnerabilities](https://sonarcloud.io/api/project_badges/measure?project=chubbyphp_chubbyphp-rate-limit&metric=vulnerabilities)](https://sonarcloud.io/dashboard?id=chubbyphp_chubbyphp-rate-limit)

## Description

A minimal rate limiting middleware for PSR 15, built on [symfony/rate-limiter][6].

## Requirements

 * php: ^8.3
 * [chubbyphp/chubbyphp-http-exception][9]: ^1.3.4
 * [psr/clock][10]: ^1.0
 * [psr/http-message][2]: ^1.1|^2.0
 * [psr/http-server-handler][3]: ^1.0.2
 * [psr/http-server-middleware][4]: ^1.0.2
 * [symfony/rate-limiter][6]: ^7.4.18|^8.1.6

## Suggest

 * [chubbyphp/chubbyphp-laminas-config-factory][5]: ^1.5.3
 * [chubbyphp/chubbyphp-trusted-proxy][7]: ^1.1.2
 * [symfony/cache][11]: ^7.4.18|^8.1.6
 * [symfony/lock][12]: ^7.4.18|^8.1.6

## Installation

Through [Composer](http://getcomposer.org) as [chubbyphp/chubbyphp-rate-limit][1].

```sh
composer require chubbyphp/chubbyphp-rate-limit "^1.0"
```

## Usage

The middleware is a thin layer on top of [symfony/rate-limiter][6]: it resolves the key of a request (usually the
client ip), consumes one token from the limiter of a `RateLimiterFactoryInterface` and translates the result into the
`RateLimit-Limit`, `RateLimit-Remaining` and `RateLimit-Reset` (seconds) headers, or throws a `429 Too Many Requests`
`HttpException` of [chubbyphp-http-exception][9] once the limiter rejects.

The header names follow the widely supported earlier drafts of [draft-ietf-httpapi-ratelimit-headers][8] (separate
`RateLimit-*` headers); the current drafts fold them into a single structured `RateLimit` header, which is not
supported by most clients yet.

```php
<?php

declare(strict_types=1);

namespace App;

use Chubbyphp\RateLimit\AttributeKeyResolver;
use Chubbyphp\RateLimit\HeaderKeyResolver;
use Chubbyphp\RateLimit\KeyResolver;
use Chubbyphp\RateLimit\RateLimitMiddleware;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

$app = ...;

$app->add(new RateLimitMiddleware(
    // the first resolved key wins, the last resolver should resolve a key for every request
    new KeyResolver(new AttributeKeyResolver('clientIp'), new HeaderKeyResolver('X-Api-Key')),
    // 100 requests per minute, see symfony/rate-limiter for the policies (fixed_window, sliding_window, token_bucket)
    // and the storages (InMemoryStorage counts within a single process only, see "Shared limits between processes")
    new RateLimiterFactory(
        ['id' => 'api', 'policy' => 'fixed_window', 'limit' => 100, 'interval' => '1 minute'],
        new InMemoryStorage(),
    ),
));
```

The token gets consumed before the handler runs: a request counts whether the handler succeeds, fails or throws (the
request itself is the cost, not its outcome). To count only certain outcomes, wrap the `RateLimiterFactoryInterface`
or run the middleware after the check which decides.

`RateLimit-Reset` (and the `reset` of the exception) is the number of seconds until the next request gets accepted,
as [symfony/rate-limiter][6] reports it: `0` as long as requests remain, the end of the window (or the time the next
token gets refilled) once they are used up. The end of the window is not reported while requests remain, as the limiter
does not expose it.

### Client ip behind a proxy

The middleware does **not** parse `X-Forwarded-For` (or any other forwarded header) itself: every proxy *appends* the
address it saw, so the first entry is whatever the client sent, and any client could pick its own key (and thereby its
own limit). Use [chubbyphp/chubbyphp-trusted-proxy][7] in front of this middleware instead: it decides which entries
of the forwarded headers to trust and sets the `clientIp` attribute, which `new AttributeKeyResolver('clientIp')`
reads.

```php
use Chubbyphp\TrustedProxy\ForwardedResolver;
use Chubbyphp\TrustedProxy\TrustedProxyMiddleware;

// the ips / cidrs of the proxies, see chubbyphp/chubbyphp-trusted-proxy
$app->add(new TrustedProxyMiddleware(new ForwardedResolver(['10.0.0.0/8', '::1'])));

// the trusted proxy middleware must run before the rate limit middleware
$app->add($rateLimitMiddleware);
```

The `clientIp` is only as trustworthy as the proxies: each of them must *set* (append to, or strip) the forwarded
headers, never pass the ones of the client through (e.g. nginx needs
`proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;`, it passes the header through by default). A proxy
which passes them through lets the client fake a trusted hop, and thereby pick its own `clientIp` (and key).

`HeaderKeyResolver` is meant for headers the client legitimately owns, like an `X-Api-Key` (the trimmed header line
is the key as is, multiple values comma joined), not for forwarded headers. Such a header must be authenticated
*before* this middleware (an authentication middleware in front rejecting unknown keys, and multiple values, as the
line `known, other` is another key), as otherwise any unknown value is a fresh key with its own limit. Keep in mind
that a client who simply omits such a header gets no key: either chain a resolver with a key every request has (e.g.
`new AttributeKeyResolver('clientIp')`, or as a last resort `new StaticKeyResolver('global')`, which puts all
remaining requests into one shared limit) behind it, or reject requests without the header before this middleware.

The shipped resolvers namespace their keys by source: `header:<lower cased name>:<value>`,
`attribute:<name>:<value>` and `static:<value>`. The same value out of two resolvers of a chain is thereby two keys
(two counters): an `X-Api-Key: 203.0.113.1` does not consume the limit of the client with the `clientIp`
`203.0.113.1`, and an `X-Api-Key: global` not the one of the `StaticKeyResolver('global')`. Own resolvers should
namespace their keys the same way, as nothing else tells the sources apart.

The middleware hashes the (namespaced) key (sha256, hex) before it reaches the `RateLimiterFactoryInterface`: the id
within the storage is `<id>-<64 hex chars>` whatever the key is (the limiter prefixes the key with the `id` of its
configuration). A client controlled value (e.g. a header of some 100 KB) can thereby neither grow the storage per
key nor hit a key restriction of a storage backend, and a secret used as key (an api key) is not stored as is. To
inspect or reset the counter of a key within the storage, hash it the same way (`hash('sha256', 'attribute:clientIp:203.0.113.1')`).

The hash bounds the size of a key, not their number: every distinct key allocates its own counter in the storage
(an entry per key until the window ends, `InMemoryStorage` only frees expired ones once the same key gets requested again).
A resolver which lets a client pick arbitrary keys (like a freely spoofable header) lets it grow the storage without
bound, which is why the key space should not be under the control of the client.

The middleware fails closed: a request without a resolved key (no matching header / attribute) is treated as a
misconfiguration and fails with a `MissingRateLimitKeyException` (a `RuntimeException`) instead of passing unlimited, as do exceptions of the limiter
(e.g. an unreachable redis of the storage; wrap the `RateLimiterFactoryInterface` to fall back to another limiter). To
exempt requests from the rate limit, do not run the middleware for them (e.g. register it per route or route group).

### Shared limits between processes

`InMemoryStorage` counts within a single process, which is only useful for long running runtimes (swoole, workerman,
...) and tests. Pass a `CacheStorage` with any PSR 6 cache pool (e.g. a redis adapter of [symfony/cache][11]) to share
the limits between the processes / servers.

The limiter reads, updates and writes a counter in three steps whatever the storage is, so concurrent requests of one
key can each read the same count: without a lock a limit of `10` may accept a few more than `10` requests arriving at
once (as many as run concurrently, `16` out of `40` in a test). Pass a lock factory of [symfony/lock][12] (the third
argument of the `RateLimiterFactory`, a store next to the storage, e.g. a `RedisStore` on the same redis) for an
exact limit, the counter of a key is then updated by one request at a time:

```php
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

$redis = RedisAdapter::createConnection('redis://localhost');

$rateLimiterFactory = new RateLimiterFactory(
    ['id' => 'api', 'policy' => 'sliding_window', 'limit' => 100, 'interval' => '1 minute'],
    new CacheStorage(new RedisAdapter($redis)),
    new LockFactory(new RedisStore($redis)),
);
```

### Limit exceeded

Once the limit is exceeded the middleware does not return a response, it throws a `429 Too Many Requests`
`HttpException` of [chubbyphp-http-exception][9], so that the exception middleware of the application (e.g. the one of
[chubbyphp-framework][13] or [chubbyphp-api][14]) turns it into the `429` response and rate limit errors look like
every other error (problem json, logging, ...). The exception carries:

 * `limit`, `remaining`, `reset` (seconds) and `retryAfter` (seconds, at least `1`) as additional problem details
   within `jsonSerialize()`
 * `detail` and `instance` name the method and path of the request only (no scheme / host, no query string, which may
   carry tokens), as they end up in the response body, logs and error trackers. The path is not filtered: a token
   within it (e.g. `/reset-password/<token>`) ends up there as well, treat such routes like a query string (a `POST`
   body instead, or a route which does not run the middleware)

The shipped exception middlewares do not set headers out of an exception, so set the `RateLimit-*` and `Retry-After`
headers of the `429` response out of the problem details (in front of the exception middleware, or within your own
one):

```php
use Chubbyphp\HttpException\HttpException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RateLimitHeadersMiddleware implements MiddlewareInterface
{
    private const array HEADERS = [
        'RateLimit-Limit' => 'limit',
        'RateLimit-Remaining' => 'remaining',
        'RateLimit-Reset' => 'reset',
        'Retry-After' => 'retryAfter',
    ];

    public function __construct(private readonly ResponseFactoryInterface $responseFactory) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (HttpException $e) {
            if (429 !== $e->getStatus()) {
                throw $e;
            }

            $data = $e->jsonSerialize();

            $response = $this->responseFactory->createResponse($e->getStatus())
                ->withHeader('Content-Type', 'application/problem+json');

            foreach (self::HEADERS as $name => $key) {
                $response = $response->withHeader($name, (string) $data[$key]);
            }

            $response->getBody()->write(json_encode($data, \JSON_THROW_ON_ERROR));

            return $response;
        }
    }
}
```

### Service factories (chubbyphp-laminas-config-factory)

The package ships service factories (built on [chubbyphp-laminas-config-factory][5]) for a PSR 11 container, configured
through `config.chubbyphp.rateLimit`:

```php
<?php

declare(strict_types=1);

namespace App;

use Chubbyphp\Laminas\Config\Config;
use Chubbyphp\Laminas\Config\ContainerFactory;
use Chubbyphp\RateLimit\KeyResolverInterface;
use Chubbyphp\RateLimit\RateLimitMiddleware;
use Chubbyphp\RateLimit\ServiceFactory\KeyResolverFactory;
use Chubbyphp\RateLimit\ServiceFactory\RateLimiterFactoryFactory;
use Chubbyphp\RateLimit\ServiceFactory\RateLimitMiddlewareFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

$container = (new ContainerFactory())(new Config([
    'chubbyphp' => [
        'rateLimit' => [
            // the key resolvers in order, the first resolved key wins
            'keys' => [
                // the clientIp attribute set by chubbyphp/chubbyphp-trusted-proxy (registered before this middleware)
                ['attribute' => 'clientIp'],
                // a header name, e.g. an api key authenticated by a middleware in front (not X-Forwarded-For, see
                // "Client ip behind a proxy")
                ['header' => 'X-Api-Key'],
                // a fixed key for all remaining requests (one shared limit instead of no limit), optional
                ['static' => 'global'],
            ],
            // every other key is an option of the symfony/rate-limiter RateLimiterFactory
            'policy' => 'fixed_window',
            'limit' => 100,
            'interval' => '1 minute',
            // 'id' => 'chubbyphp.rateLimit', ('chubbyphp.rateLimit.<name>' for named factories, see below)
        ],
    ],
    'dependencies' => [
        'factories' => [
            KeyResolverInterface::class => KeyResolverFactory::class,
            RateLimiterFactoryInterface::class => RateLimiterFactoryFactory::class,
            RateLimitMiddleware::class => RateLimitMiddlewareFactory::class,
            // the storage shared by the limiters, an InMemoryStorage if not registered
            StorageInterface::class => static fn () => new CacheStorage(...),
        ],
    ],
]));

$rateLimitMiddleware = $container->get(RateLimitMiddleware::class);
```

The `RateLimitMiddlewareFactory` uses the services `KeyResolverInterface::class` and
`RateLimiterFactoryInterface::class` of the container if registered, and creates them through the shipped
`KeyResolverFactory` and `RateLimiterFactoryFactory` otherwise. Register any of them under its name to replace it or
to share it with other services. The `RateLimiterFactoryFactory` uses the services `StorageInterface::class` (an
`InMemoryStorage` if not registered) and `LockFactory::class` (none if not registered) the same way, the
`RateLimitMiddlewareFactory` the service `ClockInterface::class` (PSR 20, the system time if not registered) as the
base for the reset seconds. The limiters of [symfony/rate-limiter][6] compute the reset out of the system time
(`microtime()`) whatever the clock is, so a clock which deviates from it (a fixed one in a test) skews the reported
seconds by the deviation.

#### With names

To serve different parts of an application with different limits, the same factories can be registered multiple
times with a name: the config is then read from `config.chubbyphp.rateLimit.<name>` and the name gets appended to each
service id.

```php
$container = (new ContainerFactory())(new Config([
    'chubbyphp' => [
        'rateLimit' => [
            'api' => [
                'keys' => [['attribute' => 'clientIp']],
                'policy' => 'fixed_window',
                'limit' => 1000,
                'interval' => '1 minute',
            ],
            'login' => [
                'keys' => [['attribute' => 'clientIp']],
                'policy' => 'sliding_window',
                'limit' => 5,
                'interval' => '15 minutes',
            ],
        ],
    ],
    'dependencies' => [
        'factories' => [
            RateLimitMiddleware::class.'api' => [RateLimitMiddlewareFactory::class, 'api'],
            RateLimitMiddleware::class.'login' => [RateLimitMiddlewareFactory::class, 'login'],
            // shared by both, unless StorageInterface::class.'api' / StorageInterface::class.'login' is registered
            StorageInterface::class => static fn () => new CacheStorage(...),
        ],
    ],
]));

$apiRateLimitMiddleware = $container->get(RateLimitMiddleware::class.'api');
$loginRateLimitMiddleware = $container->get(RateLimitMiddleware::class.'login');
```

Without an explicit `id` a named limiter uses `chubbyphp.rateLimit.<name>`, so that named limiters sharing one storage
do not share their counters. The storage, lock factory and clock services get resolved with the name appended first
(`StorageInterface::class.'login'`), and without it (`StorageInterface::class`, shared by all names) otherwise.

## Copyright

2026 Dominik Zogg

[1]: https://packagist.org/packages/chubbyphp/chubbyphp-rate-limit

[2]: https://packagist.org/packages/psr/http-message
[3]: https://packagist.org/packages/psr/http-server-handler
[4]: https://packagist.org/packages/psr/http-server-middleware
[5]: https://packagist.org/packages/chubbyphp/chubbyphp-laminas-config-factory
[6]: https://packagist.org/packages/symfony/rate-limiter
[7]: https://packagist.org/packages/chubbyphp/chubbyphp-trusted-proxy
[8]: https://datatracker.ietf.org/doc/draft-ietf-httpapi-ratelimit-headers/
[9]: https://packagist.org/packages/chubbyphp/chubbyphp-http-exception
[10]: https://packagist.org/packages/psr/clock
[11]: https://packagist.org/packages/symfony/cache
[12]: https://packagist.org/packages/symfony/lock
[13]: https://packagist.org/packages/chubbyphp/chubbyphp-framework
[14]: https://packagist.org/packages/chubbyphp/chubbyphp-api
