<?php

declare(strict_types=1);

namespace Chubbyphp\RateLimit;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the key from the given (case insensitive) header, e.g. `X-Api-Key`. The trimmed header line is the key
 * (multiple values comma joined, no list splitting), a missing or blank header resolves null.
 *
 * The key is namespaced as `header:<lower cased name>:<value>`, so that within a KeyResolver chain the same value
 * out of another header or attribute (e.g. an `X-Api-Key` equal to the `clientIp` of another client) is another key.
 *
 * The header must be authenticated before the middleware (a middleware in front rejecting unknown keys, and multiple
 * values, as the line `known, other` is another key), as otherwise any unknown value is a fresh key with its own limit
 * (and its own counter within the storage).
 *
 * Do not use it for forwarded headers like `X-Forwarded-For`: every proxy *appends* the address it saw, so the first
 * entry is whatever the client sent (freely spoofable). Use chubbyphp/chubbyphp-trusted-proxy in front and
 * `new AttributeKeyResolver('clientIp')` instead.
 */
final class HeaderKeyResolver implements KeyResolverInterface
{
    private readonly string $prefix;

    public function __construct(private readonly string $name)
    {
        // header names are case insensitive, the namespace should not depend on the spelling within the configuration
        $this->prefix = 'header:'.strtolower($name).':';
    }

    public function resolve(ServerRequestInterface $request): ?string
    {
        $key = trim($request->getHeaderLine($this->name));

        return '' !== $key ? $this->prefix.$key : null;
    }
}
