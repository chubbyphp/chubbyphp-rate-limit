<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\RateLimit\Unit\ServiceFactory;

use Chubbyphp\Mock\MockMethod\WithReturn;
use Chubbyphp\Mock\MockObjectBuilder;
use Chubbyphp\RateLimit\ServiceFactory\RateLimiterFactoryFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * @covers \Chubbyphp\RateLimit\ServiceFactory\RateLimiterFactoryFactory
 *
 * @internal
 */
final class RateLimiterFactoryFactoryTest extends TestCase
{
    private const array LIMITER_CONFIG = ['policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute'];

    public function testInvoke(): void
    {
        $builder = new MockObjectBuilder();

        /** @var ContainerInterface $container */
        $container = $builder->create(ContainerInterface::class, [
            new WithReturn('get', ['config'], [
                'chubbyphp' => [
                    'rateLimit' => ['keys' => [['attribute' => 'clientIp']], ...self::LIMITER_CONFIG],
                ],
            ]),
            new WithReturn('has', [StorageInterface::class], false),
            new WithReturn('has', [LockFactory::class], false),
        ]);

        $factory = new RateLimiterFactoryFactory();

        $service = $factory($container);

        self::assertInstanceOf(RateLimiterFactory::class, $service);

        // the keys are not an option of the limiter, the id defaults
        self::assertEquals(
            new RateLimiterFactory(['id' => 'chubbyphp.rateLimit', ...self::LIMITER_CONFIG], new InMemoryStorage()),
            $service
        );

        // counts in memory
        self::assertTrue($service->create('key')->consume()->isAccepted());
        self::assertTrue($service->create('key')->consume()->isAccepted());
        self::assertFalse($service->create('key')->consume()->isAccepted());
    }

    public function testInvokeWithId(): void
    {
        $builder = new MockObjectBuilder();

        /** @var ContainerInterface $container */
        $container = $builder->create(ContainerInterface::class, [
            new WithReturn('get', ['config'], [
                'chubbyphp' => ['rateLimit' => ['id' => 'api', ...self::LIMITER_CONFIG]],
            ]),
            new WithReturn('has', [StorageInterface::class], false),
            new WithReturn('has', [LockFactory::class], false),
        ]);

        $factory = new RateLimiterFactoryFactory();

        self::assertEquals(
            new RateLimiterFactory(['id' => 'api', ...self::LIMITER_CONFIG], new InMemoryStorage()),
            $factory($container)
        );
    }

    public function testInvokeWithRegisteredStorageAndLockFactory(): void
    {
        $builder = new MockObjectBuilder();

        /** @var StorageInterface $storage */
        $storage = $builder->create(StorageInterface::class, []);

        $lockFactory = new LockFactory(new InMemoryStore());

        /** @var ContainerInterface $container */
        $container = $builder->create(ContainerInterface::class, [
            new WithReturn('get', ['config'], ['chubbyphp' => ['rateLimit' => self::LIMITER_CONFIG]]),
            new WithReturn('has', [StorageInterface::class], true),
            new WithReturn('get', [StorageInterface::class], $storage),
            new WithReturn('has', [LockFactory::class], true),
            new WithReturn('get', [LockFactory::class], $lockFactory),
        ]);

        $factory = new RateLimiterFactoryFactory();

        self::assertEquals(
            new RateLimiterFactory(['id' => 'chubbyphp.rateLimit', ...self::LIMITER_CONFIG], $storage, $lockFactory),
            $factory($container)
        );
    }

    #[DataProvider('provideInvokeWithInvalidConfigCases')]
    public function testInvokeWithInvalidConfig(mixed $rateLimitConfig, string $message): void
    {
        $builder = new MockObjectBuilder();

        /** @var ContainerInterface $container */
        $container = $builder->create(ContainerInterface::class, [
            new WithReturn('get', ['config'], ['chubbyphp' => ['rateLimit' => $rateLimitConfig]]),
            new WithReturn('has', [StorageInterface::class], false),
            new WithReturn('has', [LockFactory::class], false),
        ]);

        $factory = new RateLimiterFactoryFactory();

        try {
            $factory($container);
            self::fail('expected an exception');
        } catch (\InvalidArgumentException $e) {
            // the messages of the options resolver vary slightly between the symfony versions (listed options)
            self::assertStringStartsWith('config.chubbyphp.rateLimit: '.$message, $e->getMessage());
            self::assertSame(0, $e->getCode());
            self::assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{0: mixed, 1: string}>
     */
    public static function provideInvokeWithInvalidConfigCases(): iterable
    {
        yield 'without policy' => [
            ['keys' => [['attribute' => 'clientIp']], 'limit' => 2, 'interval' => '1 minute'],
            'The required option "policy" is missing.',
        ];

        yield 'with unknown policy' => [
            ['policy' => 'leaky_bucket', 'limit' => 2, 'interval' => '1 minute'],
            'The option "policy" with value "leaky_bucket" is invalid. Accepted values are: "token_bucket",'
            .' "fixed_window", "sliding_window", "no_limit".',
        ];

        yield 'with unknown option' => [
            ['policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute', 'points' => 2],
            'The option "points" does not exist. Defined options are: ',
        ];

        yield 'with string limit' => [
            ['policy' => 'fixed_window', 'limit' => '2', 'interval' => '1 minute'],
            'The option "limit" with value "2" is expected to be of type "int", but is of type "string".',
        ];
    }

    public function testCallStatic(): void
    {
        $builder = new MockObjectBuilder();

        /** @var ContainerInterface $container */
        $container = $builder->create(ContainerInterface::class, [
            new WithReturn('get', ['config'], [
                'chubbyphp' => [
                    'rateLimit' => [
                        'policy' => 'fixed_window',
                        'limit' => 100,
                        'interval' => '1 minute',
                        'login' => ['keys' => [['attribute' => 'clientIp']], ...self::LIMITER_CONFIG],
                    ],
                ],
            ]),
            new WithReturn('has', [StorageInterface::class.'login'], false),
            new WithReturn('has', [StorageInterface::class], false),
            new WithReturn('has', [LockFactory::class.'login'], false),
            new WithReturn('has', [LockFactory::class], false),
        ]);

        $factory = [RateLimiterFactoryFactory::class, 'login'];

        $service = $factory($container);

        self::assertInstanceOf(RateLimiterFactory::class, $service);

        // the name is part of the default id, so that named limiters sharing one storage do not share their counters
        self::assertEquals(
            new RateLimiterFactory(['id' => 'chubbyphp.rateLimit.login', ...self::LIMITER_CONFIG], new InMemoryStorage()),
            $service
        );
    }

    public function testCallStaticWithRegisteredNamedStorageAndSharedLockFactory(): void
    {
        $builder = new MockObjectBuilder();

        /** @var StorageInterface $storage */
        $storage = $builder->create(StorageInterface::class, []);

        $lockFactory = new LockFactory(new InMemoryStore());

        /** @var ContainerInterface $container */
        $container = $builder->create(ContainerInterface::class, [
            new WithReturn('get', ['config'], ['chubbyphp' => ['rateLimit' => ['login' => self::LIMITER_CONFIG]]]),
            new WithReturn('has', [StorageInterface::class.'login'], true),
            new WithReturn('get', [StorageInterface::class.'login'], $storage),
            new WithReturn('has', [LockFactory::class.'login'], false),
            new WithReturn('has', [LockFactory::class], true),
            new WithReturn('get', [LockFactory::class], $lockFactory),
        ]);

        $factory = [RateLimiterFactoryFactory::class, 'login'];

        self::assertEquals(
            new RateLimiterFactory(['id' => 'chubbyphp.rateLimit.login', ...self::LIMITER_CONFIG], $storage, $lockFactory),
            $factory($container)
        );
    }

    public function testCallStaticWithoutConfig(): void
    {
        $builder = new MockObjectBuilder();

        /** @var ContainerInterface $container */
        $container = $builder->create(ContainerInterface::class, [
            new WithReturn('get', ['config'], ['chubbyphp' => ['rateLimit' => self::LIMITER_CONFIG]]),
            new WithReturn('has', [StorageInterface::class.'login'], false),
            new WithReturn('has', [StorageInterface::class], false),
            new WithReturn('has', [LockFactory::class.'login'], false),
            new WithReturn('has', [LockFactory::class], false),
        ]);

        $factory = [RateLimiterFactoryFactory::class, 'login'];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('config.chubbyphp.rateLimit.login: The required option "policy" is missing.');

        $factory($container);
    }
}
