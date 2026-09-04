<?php

declare(strict_types=1);

namespace Chubbyphp\RateLimit;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the key from the given request attribute, e.g. the `clientIp` set by chubbyphp/chubbyphp-trusted-proxy or
 * a `userId` set by an authentication middleware. Non string / empty attributes resolve null.
 *
 * The key is namespaced as `attribute:<name>:<value>`, so that within a KeyResolver chain the same value out of
 * another attribute or header (e.g. an `X-Api-Key` equal to the `clientIp` of another client) is another key.
 */
final class AttributeKeyResolver implements KeyResolverInterface
{
    private readonly string $prefix;

    public function __construct(private readonly string $name)
    {
        $this->prefix = 'attribute:'.$name.':';
    }

    public function resolve(ServerRequestInterface $request): ?string
    {
        $key = $request->getAttribute($this->name);

        return \is_string($key) && '' !== $key ? $this->prefix.$key : null;
    }
}
