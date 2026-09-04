<?php

declare(strict_types=1);

namespace Chubbyphp\Tests\RateLimit\Integration;

use Chubbyphp\RateLimit\RateLimitMiddleware;
use Chubbyphp\RateLimit\ServiceFactory\RateLimitMiddlewareFactory;
use Chubbyphp\RateLimit\TooManyRequestsException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * @coversNothing
 *
 * @internal
 */
final class ServiceFactoryTest extends TestCase
{
    public function testNamedMiddlewaresShareTheStorageButNotTheCounters(): void
    {
        $container = self::createContainer([
            'chubbyphp' => [
                'rateLimit' => [
                    'api' => [
                        'keys' => [['header' => 'X-Api-Key']],
                        'policy' => 'fixed_window',
                        'limit' => 100,
                        'interval' => '1 minute',
                    ],
                    'login' => [
                        'keys' => [['header' => 'X-Api-Key']],
                        'policy' => 'sliding_window',
                        'limit' => 1,
                        'interval' => '5 minutes',
                    ],
                ],
            ],
        ], [
            RateLimitMiddleware::class.'api' => [RateLimitMiddlewareFactory::class, 'api'],
            RateLimitMiddleware::class.'login' => [RateLimitMiddlewareFactory::class, 'login'],
            StorageInterface::class => static fn (): StorageInterface => new InMemoryStorage(),
        ]);

        /** @var RateLimitMiddleware $apiMiddleware */
        $apiMiddleware = $container->get(RateLimitMiddleware::class.'api');

        /** @var RateLimitMiddleware $loginMiddleware */
        $loginMiddleware = $container->get(RateLimitMiddleware::class.'login');

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };

        $request = new ServerRequest('POST', '/login', ['X-Api-Key' => 'key-1']);

        $apiResponse1 = $apiMiddleware->process($request, $handler);
        $loginResponse1 = $loginMiddleware->process($request, $handler);

        self::assertSame('100', $apiResponse1->getHeaderLine('RateLimit-Limit'));
        self::assertSame('99', $apiResponse1->getHeaderLine('RateLimit-Remaining'));
        self::assertSame('1', $loginResponse1->getHeaderLine('RateLimit-Limit'));
        self::assertSame('0', $loginResponse1->getHeaderLine('RateLimit-Remaining'));

        try {
            $loginMiddleware->process($request, $handler);
            self::fail('expected an exception');
        } catch (TooManyRequestsException $e) {
            self::assertSame(429, $e->getStatus());
        }

        // each named middleware counts with its own limiter
        self::assertSame('98', $apiMiddleware->process($request, $handler)->getHeaderLine('RateLimit-Remaining'));
    }

    /**
     * @param array<string, mixed>          $config
     * @param array<string, array|callable> $factories
     */
    private static function createContainer(array $config, array $factories): ContainerInterface
    {
        return new class($config, $factories) implements ContainerInterface {
            /** @var array<string, mixed> */
            private array $services = [];

            /**
             * @param array<string, mixed>          $config
             * @param array<string, array|callable> $factories
             */
            public function __construct(array $config, private readonly array $factories)
            {
                $this->services['config'] = $config;
            }

            public function get(string $id): mixed
            {
                if (!\array_key_exists($id, $this->services)) {
                    $factory = $this->factories[$id];

                    $this->services[$id] = \is_callable($factory) ? $factory($this) : (new $factory[0]($factory[1]))($this);
                }

                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return \array_key_exists($id, $this->services) || \array_key_exists($id, $this->factories);
            }
        };
    }
}
