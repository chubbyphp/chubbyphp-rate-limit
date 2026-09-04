<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\RateLimit\Unit;

use Chubbyphp\HttpException\HttpExceptionInterface;
use Chubbyphp\RateLimit\RateLimitInfo;
use Chubbyphp\RateLimit\TooManyRequestsException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Chubbyphp\RateLimit\TooManyRequestsException
 *
 * @internal
 */
final class TooManyRequestsExceptionTest extends TestCase
{
    public function testCreate(): void
    {
        $rateLimitInfo = new RateLimitInfo(10, 0, 3);

        $exception = TooManyRequestsException::create(
            $rateLimitInfo,
            new ServerRequest('POST', 'https://example.com/resource?token=secret')
        );

        self::assertInstanceOf(HttpExceptionInterface::class, $exception);
        self::assertInstanceOf(\RuntimeException::class, $exception);

        self::assertSame('Too Many Requests', $exception->getMessage());
        self::assertSame(429, $exception->getCode());
        self::assertNull($exception->getPrevious());

        self::assertSame('https://datatracker.ietf.org/doc/html/rfc6585#section-4', $exception->getType());
        self::assertSame(429, $exception->getStatus());
        self::assertSame('Too Many Requests', $exception->getTitle());
        // the path only: no scheme / host, and no query string (which may carry tokens)
        self::assertSame('Limit of 10 requests reached for POST /resource, retry in 3 seconds', $exception->getDetail());
        self::assertSame('/resource', $exception->getInstance());
        self::assertSame($rateLimitInfo, $exception->getRateLimitInfo());
        self::assertSame(3, $exception->getRetryAfter());

        self::assertSame([
            'RateLimit-Limit' => '10',
            'RateLimit-Remaining' => '0',
            'RateLimit-Reset' => '3',
            'Retry-After' => '3',
        ], $exception->getHeaders());

        self::assertSame([
            'type' => 'https://datatracker.ietf.org/doc/html/rfc6585#section-4',
            'status' => 429,
            'title' => 'Too Many Requests',
            'detail' => 'Limit of 10 requests reached for POST /resource, retry in 3 seconds',
            'instance' => '/resource',
            'limit' => 10,
            'remaining' => 0,
            'reset' => 3,
            'retryAfter' => 3,
        ], $exception->jsonSerialize());
    }

    public function testCreateWithinTheLastSecondTheRetryAfterIsAtLeastASecond(): void
    {
        $exception = TooManyRequestsException::create(new RateLimitInfo(10, 0, 0), new ServerRequest('GET', '/'));

        self::assertSame('Limit of 10 requests reached for GET /, retry in 1 seconds', $exception->getDetail());
        self::assertSame('/', $exception->getInstance());
        self::assertSame(1, $exception->getRetryAfter());
        self::assertSame('0', $exception->getHeaders()['RateLimit-Reset']);
        self::assertSame('1', $exception->getHeaders()['Retry-After']);
        self::assertSame(0, $exception->jsonSerialize()['reset']);
        self::assertSame(1, $exception->jsonSerialize()['retryAfter']);
    }

    public function testCreateWithoutPathUsesTheRoot(): void
    {
        $exception = TooManyRequestsException::create(
            new RateLimitInfo(10, 0, 3),
            new ServerRequest('GET', 'https://example.com')
        );

        self::assertSame('Limit of 10 requests reached for GET /, retry in 3 seconds', $exception->getDetail());
        self::assertSame('/', $exception->getInstance());
    }

    public function testConstruct(): void
    {
        $exception = new TooManyRequestsException(new RateLimitInfo(5, 0, 2), 'Slow down', '/resource');

        self::assertSame('Slow down', $exception->getDetail());
        self::assertSame('/resource', $exception->getInstance());
        self::assertSame(2, $exception->getRetryAfter());
    }
}
