<?php

declare(strict_types=1);

namespace Chubbyphp\RateLimit;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the key a request gets counted under (usually the client ip), null means "not resolved" (the next resolver
 * of a KeyResolver chain gets asked, the middleware fails if none resolves).
 */
interface KeyResolverInterface
{
    public function resolve(ServerRequestInterface $request): ?string;
}
