<?php

declare(strict_types=1);

namespace ProxyManager\Factory;

use Closure;
use OutOfBoundsException;
use ProxyManager\Configuration;
use ProxyManager\Proxy\AccessInterceptorInterface;
use ProxyManager\ProxyGenerator\AccessInterceptorScopeLocalizerGenerator;
use ProxyManager\ProxyGenerator\ProxyGeneratorInterface;
use ProxyManager\Signature\Exception\InvalidSignatureException;
use ProxyManager\Signature\Exception\MissingSignatureException;
use function get_class;

/**
 * Factory responsible of producing proxy objects
 */
class AccessInterceptorScopeLocalizerFactory extends AbstractBaseFactory
{
    private AccessInterceptorScopeLocalizerGenerator $generator;

    public function __construct(?Configuration $configuration = null)
    {
        parent::__construct($configuration);

        $this->generator = new AccessInterceptorScopeLocalizerGenerator();
    }

    /**
     * @param object    $instance           the object to be localized within the access interceptor
     * @param Closure[] $prefixInterceptors an array (indexed by method name) of interceptor closures to be called
     *                                       before method logic is executed
     * @param Closure[] $suffixInterceptors an array (indexed by method name) of interceptor closures to be called
     *                                       after method logic is executed
     *
     * @throws InvalidSignatureException
     * @throws MissingSignatureException
     * @throws OutOfBoundsException
     */
    public function createProxy(
        object $instance,
        array $prefixInterceptors = [],
        array $suffixInterceptors = []
    ) : AccessInterceptorInterface {
        $proxyClassName = $this->generateProxy(get_class($instance));

        return $proxyClassName::staticProxyConstructor($instance, $prefixInterceptors, $suffixInterceptors);
    }

    /**
     * {@inheritDoc}
     */
    protected function getGenerator() : ProxyGeneratorInterface
    {
        return $this->generator;
    }
}
