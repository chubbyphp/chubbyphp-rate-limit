<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\RateLimit\Unit\ServiceFactory;

use Chubbyphp\Mock\MockMethod\WithReturn;
use Chubbyphp\Mock\MockObjectBuilder;
use Chubbyphp\RateLimit\AttributeKeyResolver;
use Chubbyphp\RateLimit\HeaderKeyResolver;
use Chubbyphp\RateLimit\KeyResolver;
use Chubbyphp\RateLimit\ServiceFactory\KeyResolverFactory;
use Chubbyphp\RateLimit\StaticKeyResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @covers \Chubbyphp\RateLimit\ServiceFactory\KeyResolverFactory
 *
 * @internal
 */
final class KeyResolverFactoryTest extends TestCase
{
    public function testInvoke(): void
    {
        $builder = new MockObjectBuilder();

        /** @var ContainerInterface $container */
        $container = $builder->create(ContainerInterface::class, [
            new WithReturn('get', ['config'], [
                'chubbyphp' => [
                    'rateLimit' => [
                        'keys' => [['attribute' => 'clientIp'], ['header' => 'X-Api-Key'], ['static' => 'global']],
                        'policy' => 'fixed_window',
                        'limit' => 100,
                        'interval' => '1 minute',
                    ],
                ],
            ]),
        ]);

        $factory = new KeyResolverFactory();

        $service = $factory($container);

        self::assertInstanceOf(KeyResolver::class, $service);

        self::assertEquals(
            new KeyResolver(
                new AttributeKeyResolver('clientIp'),
                new HeaderKeyResolver('X-Api-Key'),
                new StaticKeyResolver('global')
            ),
            $service
        );
    }

    #[DataProvider('provideInvokeWithoutKeysCases')]
    public function testInvokeWithoutKeys(mixed $keys, string $given): void
    {
        $builder = new MockObjectBuilder();

        /** @var ContainerInterface $container */
        $container = $builder->create(ContainerInterface::class, [
            new WithReturn('get', ['config'], ['chubbyphp' => ['rateLimit' => ['keys' => $keys]]]),
        ]);

        $factory = new KeyResolverFactory();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'config.chubbyphp.rateLimit.keys must be a non empty array of key resolvers, '.$given.' given'
        );

        $factory($container);
    }

    /**
     * @return iterable<string, array{0: mixed, 1: string}>
     */
    public static function provideInvokeWithoutKeysCases(): iterable
    {
        yield 'null' => [null, 'null'];

        yield 'string' => ['clientIp', 'string'];

        yield 'empty array' => [[], 'empty array'];
    }

    public function testInvokeWithoutConfig(): void
    {
        $builder = new MockObjectBuilder();

        /** @var ContainerInterface $container */
        $container = $builder->create(ContainerInterface::class, [
            new WithReturn('get', ['config'], []),
        ]);

        $factory = new KeyResolverFactory();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'config.chubbyphp.rateLimit.keys must be a non empty array of key resolvers, null given'
        );

        $factory($container);
    }

    #[DataProvider('provideInvokeWithInvalidKeyCases')]
    public function testInvokeWithInvalidKey(mixed $key, string $message): void
    {
        $builder = new MockObjectBuilder();

        /** @var ContainerInterface $container */
        $container = $builder->create(ContainerInterface::class, [
            new WithReturn('get', ['config'], [
                'chubbyphp' => ['rateLimit' => ['keys' => [['attribute' => 'clientIp'], $key]]],
            ]),
        ]);

        $factory = new KeyResolverFactory();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('config.chubbyphp.rateLimit.keys[1]'.$message);

        $factory($container);
    }

    /**
     * @return iterable<string, array{0: mixed, 1: string}>
     */
    public static function provideInvokeWithInvalidKeyCases(): iterable
    {
        $invalid = ' must be an array with exactly one of the keys header, attribute, static, ';

        yield 'string' => ['X-Api-Key', $invalid.'string given'];

        yield 'null' => [null, $invalid.'null given'];

        yield 'empty array' => [[], $invalid.'[] given'];

        yield 'list' => [['X-Api-Key'], $invalid.'["X-Api-Key"] given'];

        yield 'unknown type' => [['heder' => 'X-Api-Key'], $invalid.'{"heder":"X-Api-Key"} given'];

        yield 'multiple types' => [
            ['header' => 'X-Api-Key', 'attribute' => 'clientIp'],
            $invalid.'{"header":"X-Api-Key","attribute":"clientIp"} given',
        ];

        yield 'int header' => [['header' => 1], '.header must be a non empty string, int given'];

        yield 'null attribute' => [['attribute' => null], '.attribute must be a non empty string, null given'];

        yield 'array static' => [['static' => ['global']], '.static must be a non empty string, array given'];

        yield 'empty header' => [['header' => ''], '.header must be a non empty string, "" given'];

        yield 'empty attribute' => [['attribute' => ''], '.attribute must be a non empty string, "" given'];

        yield 'empty static' => [['static' => ''], '.static must be a non empty string, "" given'];
    }

    public function testInvokeWithStringIndexedKeys(): void
    {
        $builder = new MockObjectBuilder();

        /** @var ContainerInterface $container */
        $container = $builder->create(ContainerInterface::class, [
            new WithReturn('get', ['config'], [
                'chubbyphp' => ['rateLimit' => ['keys' => ['ip' => ['attribute' => 'clientIp'], 'api' => 'X-Api-Key']]],
            ]),
        ]);

        $factory = new KeyResolverFactory();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'config.chubbyphp.rateLimit.keys[api] must be an array with exactly one of the keys header, attribute,'
            .' static, string given'
        );

        $factory($container);
    }

    public function testCallStatic(): void
    {
        $builder = new MockObjectBuilder();

        /** @var ContainerInterface $container */
        $container = $builder->create(ContainerInterface::class, [
            new WithReturn('get', ['config'], [
                'chubbyphp' => [
                    'rateLimit' => [
                        'keys' => [['header' => 'X-Api-Key']],
                        'login' => ['keys' => [['attribute' => 'userId']]],
                    ],
                ],
            ]),
        ]);

        $factory = [KeyResolverFactory::class, 'login'];

        $service = $factory($container);

        self::assertInstanceOf(KeyResolver::class, $service);

        self::assertEquals(new KeyResolver(new AttributeKeyResolver('userId')), $service);
    }

    public function testCallStaticWithoutKeys(): void
    {
        $builder = new MockObjectBuilder();

        /** @var ContainerInterface $container */
        $container = $builder->create(ContainerInterface::class, [
            new WithReturn('get', ['config'], [
                'chubbyphp' => ['rateLimit' => ['keys' => [['header' => 'X-Api-Key']]]],
            ]),
        ]);

        $factory = [KeyResolverFactory::class, 'login'];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'config.chubbyphp.rateLimit.login.keys must be a non empty array of key resolvers, null given'
        );

        $factory($container);
    }
}
