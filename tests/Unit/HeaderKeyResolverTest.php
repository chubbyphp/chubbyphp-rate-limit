<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\RateLimit\Unit;

use Chubbyphp\Mock\MockMethod\WithReturn;
use Chubbyphp\Mock\MockObjectBuilder;
use Chubbyphp\RateLimit\HeaderKeyResolver;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @covers \Chubbyphp\RateLimit\HeaderKeyResolver
 *
 * @internal
 */
final class HeaderKeyResolverTest extends TestCase
{
    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('provideResolveCases')]
    public function testResolve(array $headers, string $name, ?string $expected): void
    {
        $request = new ServerRequest('GET', '/', $headers);

        self::assertSame($expected, (new HeaderKeyResolver($name))->resolve($request));
    }

    /**
     * @return iterable<string, array{0: array<string, string>, 1: string, 2: null|string}>
     */
    public static function provideResolveCases(): iterable
    {
        yield 'without header' => [[], 'X-Api-Key', null];

        yield 'with empty header' => [['X-Api-Key' => ''], 'X-Api-Key', null];

        yield 'with blank header' => [['X-Api-Key' => '   '], 'X-Api-Key', null];

        yield 'with value, case insensitive, the namespace uses the lower cased name' => [
            ['x-real-ip' => '203.0.113.1'],
            'X-Real-IP',
            'header:x-real-ip:203.0.113.1',
        ];

        yield 'with value, the trimmed value is the key as is (no list splitting)' => [
            ['X-Api-Key' => ' key,with,commas '],
            'X-Api-Key',
            'header:x-api-key:key,with,commas',
        ];
    }

    public function testResolveWithMultipleValuesJoinsThem(): void
    {
        $request = (new ServerRequest('GET', '/'))->withAddedHeader('X-Api-Key', 'key-1')->withAddedHeader('X-Api-Key', 'key-2');

        self::assertSame('header:x-api-key:key-1, key-2', (new HeaderKeyResolver('X-Api-Key'))->resolve($request));
    }

    public function testResolveWithNotTrimmedHeaderLine(): void
    {
        $builder = new MockObjectBuilder();

        // PSR-7 implementations trim the values, but the resolver does not rely on it
        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, [
            new WithReturn('getHeaderLine', ['X-Api-Key'], " key-1\t"),
        ]);

        self::assertSame('header:x-api-key:key-1', (new HeaderKeyResolver('X-Api-Key'))->resolve($request));
    }
}
