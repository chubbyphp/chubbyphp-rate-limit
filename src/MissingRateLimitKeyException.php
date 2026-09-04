<?php

declare(strict_types=1);

namespace Chubbyphp\RateLimit;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Thrown by the middleware once the key resolver resolved no key for a request: a misconfiguration (the middleware
 * fails closed instead of silently passing the request unlimited), fix the resolver chain or skip the middleware for
 * such requests, never catch it to let the request through.
 *
 * The message names the method and path of the request only (no query string, which may carry tokens), as it ends
 * up in logs.
 */
final class MissingRateLimitKeyException extends \RuntimeException
{
    public static function create(ServerRequestInterface $request): self
    {
        $path = $request->getUri()->getPath();

        return new self(\sprintf(
            'Missing rate limit key for %s %s: the key resolver resolved no key, chain a resolver with a key every'
            .' request has (e.g. the "clientIp" attribute)',
            $request->getMethod(),
            '' !== $path ? $path : '/',
        ));
    }
}
