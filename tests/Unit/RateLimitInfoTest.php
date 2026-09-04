<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\RateLimit\Unit;

use Chubbyphp\RateLimit\RateLimitInfo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimit;

/**
 * @covers \Chubbyphp\RateLimit\RateLimitInfo
 *
 * @internal
 */
final class RateLimitInfoTest extends TestCase
{
    private const string NOW = '2026-01-01T12:00:00.000000+00:00';

    #[DataProvider('provideFromRateLimitCases')]
    public function testFromRateLimit(int $remainingTokens, string $retryAfter, int $expectedRemaining, int $expectedReset): void
    {
        $rateLimit = new RateLimit($remainingTokens, new \DateTimeImmutable($retryAfter), true, 10);

        $rateLimitInfo = RateLimitInfo::fromRateLimit($rateLimit, new \DateTimeImmutable(self::NOW));

        self::assertSame(10, $rateLimitInfo->limit);
        self::assertSame($expectedRemaining, $rateLimitInfo->remaining);
        self::assertSame($expectedReset, $rateLimitInfo->reset);
    }

    /**
     * @return iterable<string, array{0: int, 1: string, 2: int, 3: int}>
     */
    public static function provideFromRateLimitCases(): iterable
    {
        yield 'retry after now' => [7, self::NOW, 7, 0];

        yield 'retry after in the past (floored by the limiter)' => [7, '2026-01-01T11:59:59.500000+00:00', 7, 0];

        yield 'retry after in the future, rounded up' => [0, '2026-01-01T12:00:02.500000+00:00', 0, 3];

        yield 'retry after in the future, whole seconds' => [0, '2026-01-01T12:00:03.000000+00:00', 0, 3];

        yield 'retry after in the future, sub second' => [0, '2026-01-01T12:00:00.000001+00:00', 0, 1];

        yield 'retry after in the future, other timezone' => [0, '2026-01-01T13:00:30.000000+01:00', 0, 30];

        yield 'negative remaining tokens (rejected request counted)' => [-1, '2026-01-01T12:00:59.000000+00:00', 0, 59];
    }

    public function testToHeaders(): void
    {
        self::assertSame(
            ['RateLimit-Limit' => '10', 'RateLimit-Remaining' => '7', 'RateLimit-Reset' => '13'],
            (new RateLimitInfo(10, 7, 13))->toHeaders()
        );
    }
}
