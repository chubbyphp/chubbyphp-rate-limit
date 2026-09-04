<?php

declare(strict_types=1);

namespace Chubbyphp\RateLimit;

use Chubbyphp\HttpException\HttpExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Thrown by the middleware once the limiter rejects: a `429 Too Many Requests` http exception (as the ones of
 * chubbyphp/chubbyphp-http-exception, so that the exception middleware of the application turns it into the
 * response), carrying the `limit`, `remaining`, `reset` and `retryAfter` (seconds) as additional problem details and
 * the `RateLimit-*` and `Retry-After` headers of the `429` response as `getHeaders()` (never part of the body).
 *
 * The `detail` and `instance` name the method and path of the request only: no scheme / host, and no query string,
 * which may carry tokens and would end up in the response body, logs and error trackers otherwise.
 */
final class TooManyRequestsException extends \RuntimeException implements HttpExceptionInterface
{
    public const string TYPE = 'https://datatracker.ietf.org/doc/html/rfc6585#section-4';
    public const int STATUS = 429;
    public const string TITLE = 'Too Many Requests';

    public const string RETRY_AFTER_HEADER = 'Retry-After';

    public function __construct(
        private readonly RateLimitInfo $rateLimitInfo,
        private readonly string $detail,
        private readonly string $instance,
    ) {
        parent::__construct(self::TITLE, self::STATUS);
    }

    public static function create(RateLimitInfo $rateLimitInfo, ServerRequestInterface $request): self
    {
        $path = $request->getUri()->getPath();
        $instance = '' !== $path ? $path : '/';

        return new self(
            $rateLimitInfo,
            \sprintf(
                'Limit of %d requests reached for %s %s, retry in %d seconds',
                $rateLimitInfo->limit,
                $request->getMethod(),
                $instance,
                self::retryAfterOf($rateLimitInfo),
            ),
            $instance,
        );
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getStatus(): int
    {
        return self::STATUS;
    }

    public function getTitle(): string
    {
        return self::TITLE;
    }

    public function getDetail(): string
    {
        return $this->detail;
    }

    public function getInstance(): string
    {
        return $this->instance;
    }

    public function getRateLimitInfo(): RateLimitInfo
    {
        return $this->rateLimitInfo;
    }

    /**
     * Seconds the client should wait before retrying: the reset of the limit, but at least a second (rfc 9110).
     */
    public function getRetryAfter(): int
    {
        return self::retryAfterOf($this->rateLimitInfo);
    }

    /**
     * The `RateLimit-*` and `Retry-After` headers of the `429` response, to be set by the exception middleware.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [
            ...$this->rateLimitInfo->toHeaders(),
            self::RETRY_AFTER_HEADER => (string) $this->getRetryAfter(),
        ];
    }

    /**
     * @return array{type: string, status: int, title: string, detail: string, instance: string, limit: int, remaining: int, reset: int, retryAfter: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => self::TYPE,
            'status' => self::STATUS,
            'title' => self::TITLE,
            'detail' => $this->detail,
            'instance' => $this->instance,
            'limit' => $this->rateLimitInfo->limit,
            'remaining' => $this->rateLimitInfo->remaining,
            'reset' => $this->rateLimitInfo->reset,
            'retryAfter' => $this->getRetryAfter(),
        ];
    }

    private static function retryAfterOf(RateLimitInfo $rateLimitInfo): int
    {
        return max(1, $rateLimitInfo->reset);
    }
}
