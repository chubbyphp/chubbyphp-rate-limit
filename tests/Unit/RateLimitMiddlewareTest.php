<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\RateLimit\Unit;

use Chubbyphp\Mock\MockMethod\WithException;
use Chubbyphp\Mock\MockMethod\WithReturn;
use Chubbyphp\Mock\MockMethod\WithReturnSelf;
use Chubbyphp\Mock\MockObjectBuilder;
use Chubbyphp\RateLimit\KeyResolverInterface;
use Chubbyphp\RateLimit\MissingRateLimitKeyException;
use Chubbyphp\RateLimit\RateLimitInfo;
use Chubbyphp\RateLimit\RateLimitMiddleware;
use Chubbyphp\RateLimit\TooManyRequestsException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @covers \Chubbyphp\RateLimit\RateLimitMiddleware
 *
 * @internal
 */
final class RateLimitMiddlewareTest extends TestCase
{
    private const string NOW = '2026-01-01T12:00:00+00:00';

    private const string KEY = 'attribute:clientIp:203.0.113.1';

    // sha256 of KEY: the key gets hashed before it reaches the factory
    private const string HASHED_KEY = '971b1e9081f7ff4066bdfc0f4ba862f18153998ff3100b74bbc8f92beb4c17b9';

    public function testWithoutKey(): void
    {
        $builder = new MockObjectBuilder();

        $request = new ServerRequest('GET', 'https://example.com/resource');

        /** @var KeyResolverInterface $keyResolver */
        $keyResolver = $builder->create(KeyResolverInterface::class, [
            new WithReturn('resolve', [$request], null),
        ]);

        /** @var RateLimiterFactoryInterface $rateLimiterFactory */
        $rateLimiterFactory = $builder->create(RateLimiterFactoryInterface::class, []);

        /** @var RequestHandlerInterface $handler */
        $handler = $builder->create(RequestHandlerInterface::class, []);

        $middleware = new RateLimitMiddleware($keyResolver, $rateLimiterFactory);

        $this->expectException(MissingRateLimitKeyException::class);
        $this->expectExceptionMessage(
            'Missing rate limit key for GET /resource: the key resolver resolved no key, chain a'
            .' resolver with a key every request has (e.g. the "clientIp" attribute)'
        );

        $middleware->process($request, $handler);
    }

    public function testWithKeyWithinLimit(): void
    {
        $builder = new MockObjectBuilder();

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, []);

        /** @var ResponseInterface $response */
        $response = $builder->create(ResponseInterface::class, [
            new WithReturnSelf('withHeader', ['RateLimit-Limit', '10']),
            new WithReturnSelf('withHeader', ['RateLimit-Remaining', '7']),
            new WithReturnSelf('withHeader', ['RateLimit-Reset', '13']),
        ]);

        /** @var RequestHandlerInterface $handler */
        $handler = $builder->create(RequestHandlerInterface::class, [
            new WithReturn('handle', [$request], $response),
        ]);

        /** @var KeyResolverInterface $keyResolver */
        $keyResolver = $builder->create(KeyResolverInterface::class, [
            new WithReturn('resolve', [$request], self::KEY),
        ]);

        /** @var LimiterInterface $limiter */
        $limiter = $builder->create(LimiterInterface::class, [
            new WithReturn('consume', [1], new RateLimit(7, new \DateTimeImmutable('2026-01-01T12:00:12.5+00:00'), true, 10)),
        ]);

        /** @var RateLimiterFactoryInterface $rateLimiterFactory */
        $rateLimiterFactory = $builder->create(RateLimiterFactoryInterface::class, [
            new WithReturn('create', [self::HASHED_KEY], $limiter),
        ]);

        /** @var ClockInterface $clock */
        $clock = $builder->create(ClockInterface::class, [
            new WithReturn('now', [], new \DateTimeImmutable(self::NOW)),
        ]);

        $middleware = new RateLimitMiddleware($keyResolver, $rateLimiterFactory, $clock);

        self::assertSame($response, $middleware->process($request, $handler));
    }

    public function testWithKeyAtLimit(): void
    {
        $builder = new MockObjectBuilder();

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, []);

        /** @var ResponseInterface $response */
        $response = $builder->create(ResponseInterface::class, [
            new WithReturnSelf('withHeader', ['RateLimit-Limit', '10']),
            new WithReturnSelf('withHeader', ['RateLimit-Remaining', '0']),
            new WithReturnSelf('withHeader', ['RateLimit-Reset', '60']),
        ]);

        /** @var RequestHandlerInterface $handler */
        $handler = $builder->create(RequestHandlerInterface::class, [
            new WithReturn('handle', [$request], $response),
        ]);

        /** @var KeyResolverInterface $keyResolver */
        $keyResolver = $builder->create(KeyResolverInterface::class, [
            new WithReturn('resolve', [$request], self::KEY),
        ]);

        /** @var LimiterInterface $limiter */
        $limiter = $builder->create(LimiterInterface::class, [
            new WithReturn('consume', [1], new RateLimit(0, new \DateTimeImmutable('2026-01-01T12:01:00+00:00'), true, 10)),
        ]);

        /** @var RateLimiterFactoryInterface $rateLimiterFactory */
        $rateLimiterFactory = $builder->create(RateLimiterFactoryInterface::class, [
            new WithReturn('create', [self::HASHED_KEY], $limiter),
        ]);

        /** @var ClockInterface $clock */
        $clock = $builder->create(ClockInterface::class, [
            new WithReturn('now', [], new \DateTimeImmutable(self::NOW)),
        ]);

        $middleware = new RateLimitMiddleware($keyResolver, $rateLimiterFactory, $clock);

        self::assertSame($response, $middleware->process($request, $handler));
    }

    public function testWithKeyWithinLimitWithoutClockTheSystemTimeIsUsed(): void
    {
        $builder = new MockObjectBuilder();

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, []);

        /** @var ResponseInterface $response */
        $response = $builder->create(ResponseInterface::class, [
            new WithReturnSelf('withHeader', ['RateLimit-Limit', '10']),
            new WithReturnSelf('withHeader', ['RateLimit-Remaining', '9']),
            new WithReturnSelf('withHeader', ['RateLimit-Reset', '0']),
        ]);

        /** @var RequestHandlerInterface $handler */
        $handler = $builder->create(RequestHandlerInterface::class, [
            new WithReturn('handle', [$request], $response),
        ]);

        /** @var KeyResolverInterface $keyResolver */
        $keyResolver = $builder->create(KeyResolverInterface::class, [
            new WithReturn('resolve', [$request], self::KEY),
        ]);

        // a retry after in the past resolves a reset of 0, no matter the current system time
        /** @var LimiterInterface $limiter */
        $limiter = $builder->create(LimiterInterface::class, [
            new WithReturn('consume', [1], new RateLimit(9, new \DateTimeImmutable('2000-01-01T00:00:00+00:00'), true, 10)),
        ]);

        /** @var RateLimiterFactoryInterface $rateLimiterFactory */
        $rateLimiterFactory = $builder->create(RateLimiterFactoryInterface::class, [
            new WithReturn('create', [self::HASHED_KEY], $limiter),
        ]);

        $middleware = new RateLimitMiddleware($keyResolver, $rateLimiterFactory);

        self::assertSame($response, $middleware->process($request, $handler));
    }

    public function testWithKeyExceeded(): void
    {
        $builder = new MockObjectBuilder();

        $request = new ServerRequest('GET', 'https://example.com/resource');

        /** @var RequestHandlerInterface $handler */
        $handler = $builder->create(RequestHandlerInterface::class, []);

        /** @var KeyResolverInterface $keyResolver */
        $keyResolver = $builder->create(KeyResolverInterface::class, [
            new WithReturn('resolve', [$request], self::KEY),
        ]);

        /** @var LimiterInterface $limiter */
        $limiter = $builder->create(LimiterInterface::class, [
            new WithReturn('consume', [1], new RateLimit(-1, new \DateTimeImmutable('2026-01-01T12:00:02.5+00:00'), false, 10)),
        ]);

        /** @var RateLimiterFactoryInterface $rateLimiterFactory */
        $rateLimiterFactory = $builder->create(RateLimiterFactoryInterface::class, [
            new WithReturn('create', [self::HASHED_KEY], $limiter),
        ]);

        /** @var ClockInterface $clock */
        $clock = $builder->create(ClockInterface::class, [
            new WithReturn('now', [], new \DateTimeImmutable(self::NOW)),
        ]);

        $middleware = new RateLimitMiddleware($keyResolver, $rateLimiterFactory, $clock);

        try {
            $middleware->process($request, $handler);
            self::fail('expected an exception');
        } catch (TooManyRequestsException $e) {
            self::assertSame(429, $e->getStatus());
            self::assertSame(
                'Limit of 10 requests reached for GET /resource, retry in 3 seconds',
                $e->getDetail()
            );
            self::assertSame('/resource', $e->getInstance());
            self::assertEquals(new RateLimitInfo(10, 0, 3), $e->getRateLimitInfo());
            self::assertSame(3, $e->getRetryAfter());
            self::assertSame([
                'RateLimit-Limit' => '10',
                'RateLimit-Remaining' => '0',
                'RateLimit-Reset' => '3',
                'Retry-After' => '3',
            ], $e->getHeaders());
        }
    }

    public function testWithKeyExceededWithinTheLastSecond(): void
    {
        $builder = new MockObjectBuilder();

        $request = new ServerRequest('GET', 'https://example.com/resource');

        /** @var RequestHandlerInterface $handler */
        $handler = $builder->create(RequestHandlerInterface::class, []);

        /** @var KeyResolverInterface $keyResolver */
        $keyResolver = $builder->create(KeyResolverInterface::class, [
            new WithReturn('resolve', [$request], self::KEY),
        ]);

        /** @var LimiterInterface $limiter */
        $limiter = $builder->create(LimiterInterface::class, [
            new WithReturn('consume', [1], new RateLimit(0, new \DateTimeImmutable(self::NOW), false, 10)),
        ]);

        /** @var RateLimiterFactoryInterface $rateLimiterFactory */
        $rateLimiterFactory = $builder->create(RateLimiterFactoryInterface::class, [
            new WithReturn('create', [self::HASHED_KEY], $limiter),
        ]);

        /** @var ClockInterface $clock */
        $clock = $builder->create(ClockInterface::class, [
            new WithReturn('now', [], new \DateTimeImmutable(self::NOW)),
        ]);

        $middleware = new RateLimitMiddleware($keyResolver, $rateLimiterFactory, $clock);

        try {
            $middleware->process($request, $handler);
            self::fail('expected an exception');
        } catch (TooManyRequestsException $e) {
            self::assertEquals(new RateLimitInfo(10, 0, 0), $e->getRateLimitInfo());
            self::assertSame(1, $e->getRetryAfter());
        }
    }

    public function testWithKeyWithLimiterException(): void
    {
        $builder = new MockObjectBuilder();

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, []);

        /** @var RequestHandlerInterface $handler */
        $handler = $builder->create(RequestHandlerInterface::class, []);

        /** @var KeyResolverInterface $keyResolver */
        $keyResolver = $builder->create(KeyResolverInterface::class, [
            new WithReturn('resolve', [$request], self::KEY),
        ]);

        $exception = new \RuntimeException('storage unreachable');

        /** @var LimiterInterface $limiter */
        $limiter = $builder->create(LimiterInterface::class, [
            new WithException('consume', [1], $exception),
        ]);

        /** @var RateLimiterFactoryInterface $rateLimiterFactory */
        $rateLimiterFactory = $builder->create(RateLimiterFactoryInterface::class, [
            new WithReturn('create', [self::HASHED_KEY], $limiter),
        ]);

        /** @var ClockInterface $clock */
        $clock = $builder->create(ClockInterface::class, []);

        $middleware = new RateLimitMiddleware($keyResolver, $rateLimiterFactory, $clock);

        try {
            $middleware->process($request, $handler);
            self::fail('expected an exception');
        } catch (\RuntimeException $e) {
            self::assertSame($exception, $e);
        }
    }

    public function testWithKeyWithHandlerException(): void
    {
        $builder = new MockObjectBuilder();

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, []);

        $exception = new \RuntimeException('handler failed');

        /** @var RequestHandlerInterface $handler */
        $handler = $builder->create(RequestHandlerInterface::class, [
            new WithException('handle', [$request], $exception),
        ]);

        /** @var KeyResolverInterface $keyResolver */
        $keyResolver = $builder->create(KeyResolverInterface::class, [
            new WithReturn('resolve', [$request], self::KEY),
        ]);

        /** @var LimiterInterface $limiter */
        $limiter = $builder->create(LimiterInterface::class, [
            new WithReturn('consume', [1], new RateLimit(9, new \DateTimeImmutable(self::NOW), true, 10)),
        ]);

        /** @var RateLimiterFactoryInterface $rateLimiterFactory */
        $rateLimiterFactory = $builder->create(RateLimiterFactoryInterface::class, [
            new WithReturn('create', [self::HASHED_KEY], $limiter),
        ]);

        /** @var ClockInterface $clock */
        $clock = $builder->create(ClockInterface::class, [
            new WithReturn('now', [], new \DateTimeImmutable(self::NOW)),
        ]);

        $middleware = new RateLimitMiddleware($keyResolver, $rateLimiterFactory, $clock);

        try {
            $middleware->process($request, $handler);
            self::fail('expected an exception');
        } catch (\RuntimeException $e) {
            self::assertSame($exception, $e);
        }
    }
}
