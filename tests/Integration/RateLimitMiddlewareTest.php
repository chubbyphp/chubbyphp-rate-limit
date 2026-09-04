<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\RateLimit\Integration;

use Chubbyphp\RateLimit\AttributeKeyResolver;
use Chubbyphp\RateLimit\HeaderKeyResolver;
use Chubbyphp\RateLimit\KeyResolver;
use Chubbyphp\RateLimit\MissingRateLimitKeyException;
use Chubbyphp\RateLimit\RateLimitMiddleware;
use Chubbyphp\RateLimit\TooManyRequestsException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\RateLimiter\LimiterStateInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * @coversNothing
 *
 * @internal
 */
final class RateLimitMiddlewareTest extends TestCase
{
    public function testWithFixedWindow(): void
    {
        $middleware = new RateLimitMiddleware(
            new KeyResolver(new HeaderKeyResolver('X-Api-Key'), new AttributeKeyResolver('clientIp')),
            new RateLimiterFactory(
                ['id' => 'test', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute'],
                new InMemoryStorage()
            )
        );

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                // the ones of the handler get replaced
                return (new Response(201))->withHeader('RateLimit-Limit', 'stale');
            }
        };

        $byHeader = new ServerRequest('GET', '/resource', ['X-Api-Key' => '203.0.113.1']);
        $byAttribute = (new ServerRequest('GET', '/resource'))->withAttribute('clientIp', '203.0.113.1');
        $other = new ServerRequest('GET', '/resource', ['X-Api-Key' => 'key-2']);

        $response1 = $middleware->process($byHeader, $handler);

        self::assertSame(201, $response1->getStatusCode());
        self::assertSame('2', $response1->getHeaderLine('RateLimit-Limit'));
        self::assertSame('1', $response1->getHeaderLine('RateLimit-Remaining'));
        self::assertSame('0', $response1->getHeaderLine('RateLimit-Reset'));

        // the same value out of another resolver is another key: a header equal to the client ip of another client
        // does not consume the limit of that client
        self::assertSame('1', $middleware->process($byAttribute, $handler)->getHeaderLine('RateLimit-Remaining'));

        $response2 = $middleware->process($byHeader, $handler);

        self::assertSame('0', $response2->getHeaderLine('RateLimit-Remaining'));
        self::assertGreaterThan(0, (int) $response2->getHeaderLine('RateLimit-Reset'));
        self::assertLessThanOrEqual(60, (int) $response2->getHeaderLine('RateLimit-Reset'));

        try {
            $middleware->process($byHeader, $handler);
            self::fail('expected an exception');
        } catch (TooManyRequestsException $e) {
            self::assertSame(429, $e->getStatus());
            self::assertSame(2, $e->getRateLimitInfo()->limit);
            self::assertSame(0, $e->getRateLimitInfo()->remaining);
            self::assertGreaterThan(0, $e->getRateLimitInfo()->reset);
            self::assertLessThanOrEqual(60, $e->getRateLimitInfo()->reset);
            self::assertSame((string) $e->getRateLimitInfo()->reset, $e->getHeaders()['Retry-After']);
        }

        // another key has its own limit
        self::assertSame('1', $middleware->process($other, $handler)->getHeaderLine('RateLimit-Remaining'));
    }

    public function testWithHashedKey(): void
    {
        $storage = new class implements StorageInterface {
            /**
             * @var array<string, LimiterStateInterface>
             */
            public array $states = [];

            public function save(LimiterStateInterface $limiterState): void
            {
                $this->states[$limiterState->getId()] = $limiterState;
            }

            public function fetch(string $limiterStateId): ?LimiterStateInterface
            {
                return $this->states[$limiterStateId] ?? null;
            }

            public function delete(string $limiterStateId): void
            {
                unset($this->states[$limiterStateId]);
            }
        };

        $middleware = new RateLimitMiddleware(
            new KeyResolver(new HeaderKeyResolver('X-Api-Key')),
            new RateLimiterFactory(
                ['id' => 'test', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute'],
                $storage
            )
        );

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };

        // a client controlled key of 100 KB
        $value = str_repeat('a', 100_000);

        $middleware->process(new ServerRequest('GET', '/resource', ['X-Api-Key' => $value]), $handler);
        $middleware->process(new ServerRequest('GET', '/resource', ['X-Api-Key' => ' '.$value.' ']), $handler);

        // the id within the storage is the hash of the namespaced key, not the value: its size does not depend on the
        // key, and the value is not stored as is
        self::assertSame(['test-'.hash('sha256', 'header:x-api-key:'.$value)], array_keys($storage->states));

        try {
            $middleware->process(new ServerRequest('GET', '/resource', ['X-Api-Key' => $value]), $handler);
            self::fail('expected an exception');
        } catch (TooManyRequestsException $e) {
            self::assertSame(429, $e->getStatus());
        }
    }

    public function testWithoutKey(): void
    {
        $middleware = new RateLimitMiddleware(
            new KeyResolver(new HeaderKeyResolver('X-Api-Key')),
            new RateLimiterFactory(
                ['id' => 'test', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute'],
                new InMemoryStorage()
            )
        );

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };

        $this->expectException(MissingRateLimitKeyException::class);
        $this->expectExceptionMessage('Missing rate limit key for GET /resource');

        $middleware->process(new ServerRequest('GET', '/resource'), $handler);
    }
}
