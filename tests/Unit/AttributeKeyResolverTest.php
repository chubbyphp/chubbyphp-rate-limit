<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\RateLimit\Unit;

use Chubbyphp\RateLimit\AttributeKeyResolver;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Chubbyphp\RateLimit\AttributeKeyResolver
 *
 * @internal
 */
final class AttributeKeyResolverTest extends TestCase
{
    /**
     * @param array<string, mixed> $attributes
     */
    #[DataProvider('provideResolveCases')]
    public function testResolve(array $attributes, ?string $expected): void
    {
        $request = new ServerRequest('GET', '/');

        foreach ($attributes as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        self::assertSame($expected, (new AttributeKeyResolver('clientIp'))->resolve($request));
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: null|string}>
     */
    public static function provideResolveCases(): iterable
    {
        yield 'without attribute' => [[], null];

        yield 'with null attribute' => [['clientIp' => null], null];

        yield 'with empty attribute' => [['clientIp' => ''], null];

        yield 'with non string attribute' => [['clientIp' => 42], null];

        yield 'with other attribute' => [['userId' => 'user-1'], null];

        yield 'with attribute' => [['clientIp' => '203.0.113.1'], 'attribute:clientIp:203.0.113.1'];
    }
}
