<?php

declare(strict_types=1);

namespace Chubbyphp\RateLimit;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the given value as the key for every request, e.g. `global` as the last resolver of a chain: requests
 * without any other key then share one (global) limit instead of passing unlimited. An empty value gets rejected, as
 * the other resolvers never resolve one.
 *
 * The key is namespaced as `static:<value>`, so that within a KeyResolver chain it never collides with a header or
 * attribute value (e.g. a client sending `X-Api-Key: global`).
 */
final class StaticKeyResolver implements KeyResolverInterface
{
    private readonly string $key;

    public function __construct(string $value)
    {
        if ('' === $value) {
            throw new \InvalidArgumentException('value must not be empty');
        }

        $this->key = 'static:'.$value;
    }

    public function resolve(ServerRequestInterface $request): string
    {
        return $this->key;
    }
}
