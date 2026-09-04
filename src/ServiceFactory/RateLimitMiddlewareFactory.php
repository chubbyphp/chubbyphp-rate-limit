<?php

declare(strict_types=1);

namespace Chubbyphp\RateLimit\ServiceFactory;

use Chubbyphp\Laminas\Config\Factory\AbstractFactory;
use Chubbyphp\RateLimit\KeyResolverInterface;
use Chubbyphp\RateLimit\RateLimitMiddleware;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Uses the services `KeyResolverInterface::class` and `RateLimiterFactoryInterface::class` (each with the name
 * appended for named factories) of the container if registered, and creates them through the shipped
 * KeyResolverFactory and RateLimiterFactoryFactory otherwise. The clock is the service `ClockInterface::class` (the
 * named one if registered, the unnamed one otherwise), the system time if neither is registered.
 */
final class RateLimitMiddlewareFactory extends AbstractFactory
{
    public function __invoke(ContainerInterface $container): RateLimitMiddleware
    {
        /** @var KeyResolverInterface $keyResolver */
        $keyResolver = $this->resolveDependency($container, KeyResolverInterface::class, KeyResolverFactory::class);

        /** @var RateLimiterFactoryInterface $rateLimiterFactory */
        $rateLimiterFactory = $this->resolveDependency(
            $container,
            RateLimiterFactoryInterface::class,
            RateLimiterFactoryFactory::class
        );

        return new RateLimitMiddleware($keyResolver, $rateLimiterFactory, $this->resolveClock($container));
    }

    private function resolveClock(ContainerInterface $container): ?ClockInterface
    {
        foreach (array_unique([ClockInterface::class.$this->name, ClockInterface::class]) as $id) {
            if ($container->has($id)) {
                /** @var ClockInterface */
                return $container->get($id);
            }
        }

        return null;
    }
}
