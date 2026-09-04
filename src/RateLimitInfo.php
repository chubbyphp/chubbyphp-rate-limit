<?php

declare(strict_types=1);

namespace Chubbyphp\RateLimit;

use Symfony\Component\RateLimiter\RateLimit;

/**
 * The state of the limit after consuming a request, as the middleware sets it as `RateLimit-*` headers.
 */
final readonly class RateLimitInfo
{
    public const string LIMIT_HEADER = 'RateLimit-Limit';
    public const string REMAINING_HEADER = 'RateLimit-Remaining';
    public const string RESET_HEADER = 'RateLimit-Reset';

    /**
     * @param int $limit     the configured maximum of requests per window
     * @param int $remaining requests left within the current window, 0 when exceeded
     * @param int $reset     seconds until the current window ends (rounded up), 0 if it already ended
     */
    public function __construct(
        public int $limit,
        public int $remaining,
        public int $reset,
    ) {}

    /**
     * @param \DateTimeImmutable $now the moment the request got consumed, the base for the reset seconds
     */
    public static function fromRateLimit(RateLimit $rateLimit, \DateTimeImmutable $now): self
    {
        $retryAfter = $rateLimit->getRetryAfter();

        $seconds = $retryAfter->getTimestamp() - $now->getTimestamp();

        // rounded up: a larger fraction of a second (zero padded microseconds) counts as a further second
        if ($retryAfter->format('u') > $now->format('u')) {
            ++$seconds;
        }

        return new self(
            $rateLimit->getLimit(),
            // a rejected request still gets counted by some policies, so the remaining tokens can be negative
            max(0, $rateLimit->getRemainingTokens()),
            max(0, $seconds),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toHeaders(): array
    {
        return [
            self::LIMIT_HEADER => (string) $this->limit,
            self::REMAINING_HEADER => (string) $this->remaining,
            self::RESET_HEADER => (string) $this->reset,
        ];
    }
}
