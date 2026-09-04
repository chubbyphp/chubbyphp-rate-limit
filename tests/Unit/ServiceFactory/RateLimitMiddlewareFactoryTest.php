<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\RateLimit\Unit\ServiceFactory;

use Chubbyphp\Mock\MockMethod\WithReturn;
use Chubbyphp\Mock\MockObjectBuilder;
use Chubbyphp\RateLimit\AttributeKeyResolver;
use Chubbyphp\RateLimit\KeyResolver;
use Chubbyphp\RateLimit\KeyResolverInterface;
use Chubbyphp\RateLimit\RateLimitMiddleware;
use Chubbyphp\RateLimit\ServiceFactory\RateLimitMiddlewareFactory;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * @covers \Chubbyphp\RateLimit\ServiceFactory\RateLimitMiddlewareFactory
 *
 * @internal
 */
final class RateLimitMiddlewareFactoryTest extends TestCase
{
    public function testInvoke(): void
    {
        $builder = new MockObjectBuilder();

        /** @var KeyResolverInterface $keyResolver */
        $keyResolver = $builder->create(KeyResolverInterface::class, []);

        /** @var RateLimiterFactoryInterface $rateLimiterFactory */
        $rateLimiterFactory = $builder->create(RateLimiterFactoryInterface::class, []);

        /** @var ClockInterface $clock */
        $clock = $builder->create(ClockInterface::class, []);

        /** @var ContainerInterface $container */
        $container = $builder->create(ContainerInterface::class, [
            new WithReturn('has', [KeyResolverInterface::class], true),
            new WithReturn('get', [KeyResolverInterface::class], $keyResolver),
            new WithReturn('has', [RateLimiterFactoryInterface::class], true),
            new WithReturn('get', [RateLimiterFactoryInterface::class], $rateLimiterFactory),
            new WithReturn('has', [ClockInterface::class], true),
            new WithReturn('get', [ClockInterface::class], $clock),
        ]);

        $factory = new RateLimitMiddlewareFactory();

        $service = $factory($container);

        self::assertInstanceOf(RateLimitMiddleware::class, $service);

        self::assertEquals(new RateLimitMiddleware($keyResolver, $rateLimiterFactory, $clock), $service);
    }

    public function testInvokeWithoutRegisteredServices(): void
    {
        $builder = new MockObjectBuilder();

        $config = [
            'chubbyphp' => [
                'rateLimit' => [
                    'keys' => [['attribute' => 'clientIp']],
                    'policy' => 'fixed_window',
                    'limit' => 2,
                    'interval' => '1 minute',
                ],
            ],
        ];

        /** @var ContainerInterface $container */
        $container = $builder->create(ContainerInterface::class, [
            new WithReturn('has', [KeyResolverInterface::class], false),
            new WithReturn('get', ['config'], $config),
            new WithReturn('has', [RateLimiterFactoryInterface::class], false),
            new WithReturn('get', ['config'], $config),
            new WithReturn('has', [StorageInterface::class], false),
            new WithReturn('has', [LockFactory::class], false),
            new WithReturn('has', [ClockInterface::class], false),
        ]);

        $factory = new RateLimitMiddlewareFactory();

        $service = $factory($container);

        self::assertInstanceOf(RateLimitMiddleware::class, $service);

        self::assertEquals(
            new RateLimitMiddleware(
                new KeyResolver(new AttributeKeyResolver('clientIp')),
                new RateLimiterFactory(
                    ['id' => 'chubbyphp.rateLimit', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute'],
                    new InMemoryStorage()
                ),
            ),
            $service
        );
    }

    public function testCallStatic(): void
    {
        $builder = new MockObjectBuilder();

        /** @var KeyResolverInterface $keyResolver */
        $keyResolver = $builder->create(KeyResolverInterface::class, []);

        /** @var RateLimiterFactoryInterface $rateLimiterFactory */
        $rateLimiterFactory = $builder->create(RateLimiterFactoryInterface::class, []);

        /** @var ClockInterface $clock */
        $clock = $builder->create(ClockInterface::class, []);

        /** @var ContainerInterface $container */
        $container = $builder->create(ContainerInterface::class, [
            new WithReturn('has', [KeyResolverInterface::class.'login'], true),
            new WithReturn('get', [KeyResolverInterface::class.'login'], $keyResolver),
            new WithReturn('has', [RateLimiterFactoryInterface::class.'login'], true),
            new WithReturn('get', [RateLimiterFactoryInterface::class.'login'], $rateLimiterFactory),
            new WithReturn('has', [ClockInterface::class.'login'], false),
            new WithReturn('has', [ClockInterface::class], true),
            new WithReturn('get', [ClockInterface::class], $clock),
        ]);

        $factory = [RateLimitMiddlewareFactory::class, 'login'];

        $service = $factory($container);

        self::assertInstanceOf(RateLimitMiddleware::class, $service);

        self::assertEquals(new RateLimitMiddleware($keyResolver, $rateLimiterFactory, $clock), $service);
    }

    public function testCallStaticWithNamedClock(): void
    {
        $builder = new MockObjectBuilder();

        /** @var KeyResolverInterface $keyResolver */
        $keyResolver = $builder->create(KeyResolverInterface::class, []);

        /** @var RateLimiterFactoryInterface $rateLimiterFactory */
        $rateLimiterFactory = $builder->create(RateLimiterFactoryInterface::class, []);

        /** @var ClockInterface $clock */
        $clock = $builder->create(ClockInterface::class, []);

        /** @var ContainerInterface $container */
        $container = $builder->create(ContainerInterface::class, [
            new WithReturn('has', [KeyResolverInterface::class.'login'], true),
            new WithReturn('get', [KeyResolverInterface::class.'login'], $keyResolver),
            new WithReturn('has', [RateLimiterFactoryInterface::class.'login'], true),
            new WithReturn('get', [RateLimiterFactoryInterface::class.'login'], $rateLimiterFactory),
            new WithReturn('has', [ClockInterface::class.'login'], true),
            new WithReturn('get', [ClockInterface::class.'login'], $clock),
        ]);

        $factory = [RateLimitMiddlewareFactory::class, 'login'];

        self::assertEquals(new RateLimitMiddleware($keyResolver, $rateLimiterFactory, $clock), $factory($container));
    }
}
