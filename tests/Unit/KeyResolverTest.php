<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\RateLimit\Unit;

use Chubbyphp\Mock\MockMethod\WithReturn;
use Chubbyphp\Mock\MockObjectBuilder;
use Chubbyphp\RateLimit\KeyResolver;
use Chubbyphp\RateLimit\KeyResolverInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @covers \Chubbyphp\RateLimit\KeyResolver
 *
 * @internal
 */
final class KeyResolverTest extends TestCase
{
    public function testWithoutResolvers(): void
    {
        $builder = new MockObjectBuilder();

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, []);

        self::assertNull((new KeyResolver())->resolve($request));
    }

    public function testWithoutMatch(): void
    {
        $builder = new MockObjectBuilder();

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, []);

        /** @var KeyResolverInterface $keyResolver1 */
        $keyResolver1 = $builder->create(KeyResolverInterface::class, [
            new WithReturn('resolve', [$request], null),
        ]);

        /** @var KeyResolverInterface $keyResolver2 */
        $keyResolver2 = $builder->create(KeyResolverInterface::class, [
            new WithReturn('resolve', [$request], null),
        ]);

        self::assertNull((new KeyResolver($keyResolver1, $keyResolver2))->resolve($request));
    }

    public function testWithMatchTheFirstKeyWins(): void
    {
        $builder = new MockObjectBuilder();

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, []);

        /** @var KeyResolverInterface $keyResolver1 */
        $keyResolver1 = $builder->create(KeyResolverInterface::class, [
            new WithReturn('resolve', [$request], null),
        ]);

        /** @var KeyResolverInterface $keyResolver2 */
        $keyResolver2 = $builder->create(KeyResolverInterface::class, [
            new WithReturn('resolve', [$request], '203.0.113.1'),
        ]);

        // never asked
        /** @var KeyResolverInterface $keyResolver3 */
        $keyResolver3 = $builder->create(KeyResolverInterface::class, []);

        self::assertSame(
            '203.0.113.1',
            (new KeyResolver($keyResolver1, $keyResolver2, $keyResolver3))->resolve($request)
        );
    }
}
