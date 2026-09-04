<?php

declare(strict_types=1);

namespace Chubbyphp\RateLimit;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Chains the given resolvers, the first resolved key wins, null if none resolves.
 */
final class KeyResolver implements KeyResolverInterface
{
    /**
     * @var array<KeyResolverInterface>
     */
    private readonly array $keyResolvers;

    public function __construct(KeyResolverInterface ...$keyResolvers)
    {
        $this->keyResolvers = $keyResolvers;
    }

    public function resolve(ServerRequestInterface $request): ?string
    {
        foreach ($this->keyResolvers as $keyResolver) {
            $key = $keyResolver->resolve($request);

            if (null !== $key) {
                return $key;
            }
        }

        return null;
    }
}
