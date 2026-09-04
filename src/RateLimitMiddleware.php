<?php

declare(strict_types=1);

namespace Chubbyphp\RateLimit;

use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Consumes one token per request from the limiter the `rateLimiterFactory` creates for the key of the `keyResolver`.
 * Every response carries the `RateLimit-Limit`, `RateLimit-Remaining` and `RateLimit-Reset` headers (see
 * RateLimitInfo), set (not added), so that the ones of the handler get replaced.
 *
 * The key gets hashed (sha256, hex) before it reaches the factory: the id within the storage is then
 * `<id>-<64 hex chars>` whatever the key is, so that a client controlled value (e.g. a header of some 100 KB) can
 * neither grow the storage per key nor hit a key restriction of a storage backend, and a secret used as key (an api
 * key) is not stored as is. The hash does not bound the *number* of keys: a client which can pick arbitrary keys
 * still allocates a counter per key (see HeaderKeyResolver).
 *
 * Once the limiter rejects, a TooManyRequestsException gets thrown (with the RateLimitInfo as data, and the
 * `RateLimit-*` and `Retry-After` headers of the `429` response as `getHeaders()`), the exception middleware of the
 * application turns it into the `429` response and should add the headers to it.
 *
 * A request without key (the `keyResolver` resolved null) is a misconfiguration and fails with a
 * MissingRateLimitKeyException, instead of silently passing unlimited: chain a resolver with a key every request has (e.g. the `clientIp` attribute) behind
 * the optional ones, or skip the middleware for such requests. Any other exception of the limiter (e.g. an
 * unreachable redis of the storage) gets rethrown as well (fail closed), wrap the factory to fail open instead.
 *
 * The `clock` is the base for the reset seconds (`RateLimit-Reset`, `Retry-After`), the system time by default. The
 * limiters of symfony/rate-limiter compute the reset out of the system time (`microtime()`) whatever the clock is, so
 * a clock which deviates from it (a fixed one in a test) skews the reported seconds by the deviation.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly KeyResolverInterface $keyResolver,
        private readonly RateLimiterFactoryInterface $rateLimiterFactory,
        private readonly ?ClockInterface $clock = null,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $key = $this->keyResolver->resolve($request);

        if (null === $key) {
            throw MissingRateLimitKeyException::create($request);
        }

        $rateLimit = $this->rateLimiterFactory->create(hash('sha256', $key))->consume();

        $rateLimitInfo = RateLimitInfo::fromRateLimit($rateLimit, $this->clock?->now() ?? new \DateTimeImmutable());

        if (!$rateLimit->isAccepted()) {
            throw TooManyRequestsException::create($rateLimitInfo, $request);
        }

        $response = $handler->handle($request);

        foreach ($rateLimitInfo->toHeaders() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
