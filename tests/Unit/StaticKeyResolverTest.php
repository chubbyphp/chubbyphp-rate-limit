<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\RateLimit\Unit;

use Chubbyphp\Mock\MockObjectBuilder;
use Chubbyphp\RateLimit\StaticKeyResolver;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @covers \Chubbyphp\RateLimit\StaticKeyResolver
 *
 * @internal
 */
final class StaticKeyResolverTest extends TestCase
{
    public function testResolve(): void
    {
        $builder = new MockObjectBuilder();

        /** @var ServerRequestInterface $request */
        $request = $builder->create(ServerRequestInterface::class, []);

        self::assertSame('static:global', (new StaticKeyResolver('global'))->resolve($request));
    }

    public function testWithEmptyValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('value must not be empty');

        new StaticKeyResolver('');
    }
}
