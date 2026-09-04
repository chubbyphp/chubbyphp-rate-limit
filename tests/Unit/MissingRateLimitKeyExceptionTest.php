<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\RateLimit\Unit;

use Chubbyphp\RateLimit\MissingRateLimitKeyException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Chubbyphp\RateLimit\MissingRateLimitKeyException
 *
 * @internal
 */
final class MissingRateLimitKeyExceptionTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = MissingRateLimitKeyException::create(
            new ServerRequest('POST', 'https://example.com/resource?token=secret')
        );

        self::assertInstanceOf(\RuntimeException::class, $exception);

        // the path only: no scheme / host, and no query string (which may carry tokens)
        self::assertSame(
            'Missing rate limit key for POST /resource: the key resolver resolved no key,'
            .' chain a resolver with a key every request has (e.g. the "clientIp" attribute)',
            $exception->getMessage()
        );
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
    }

    public function testCreateWithoutPathUsesTheRoot(): void
    {
        $exception = MissingRateLimitKeyException::create(new ServerRequest('GET', 'https://example.com'));

        self::assertStringStartsWith('Missing rate limit key for GET /: ', $exception->getMessage());
    }
}
