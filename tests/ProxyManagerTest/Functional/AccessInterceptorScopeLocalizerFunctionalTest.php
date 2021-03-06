<?php

declare(strict_types=1);

namespace ProxyManagerTest\Functional;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ProxyManager\Configuration;
use ProxyManager\Exception\UnsupportedProxiedClassException;
use ProxyManager\Factory\AccessInterceptorScopeLocalizerFactory;
use ProxyManager\Generator\ClassGenerator;
use ProxyManager\Generator\Util\UniqueIdentifierGenerator;
use ProxyManager\GeneratorStrategy\EvaluatingGeneratorStrategy;
use ProxyManager\Proxy\AccessInterceptorInterface;
use ProxyManager\ProxyGenerator\AccessInterceptorScopeLocalizerGenerator;
use ProxyManager\ProxyGenerator\Util\Properties;
use ProxyManagerTest\Assert;
use ProxyManagerTestAsset\BaseClass;
use ProxyManagerTestAsset\ClassWithCounterConstructor;
use ProxyManagerTestAsset\ClassWithDynamicArgumentsMethod;
use ProxyManagerTestAsset\ClassWithMethodWithByRefVariadicFunction;
use ProxyManagerTestAsset\ClassWithMethodWithVariadicFunction;
use ProxyManagerTestAsset\ClassWithParentHint;
use ProxyManagerTestAsset\ClassWithPublicArrayProperty;
use ProxyManagerTestAsset\ClassWithPublicProperties;
use ProxyManagerTestAsset\ClassWithSelfHint;
use ProxyManagerTestAsset\EmptyClass;
use ProxyManagerTestAsset\VoidCounter;
use ReflectionClass;
use stdClass;
use function array_values;
use function get_class;
use function random_int;
use function serialize;
use function uniqid;
use function unserialize;

/**
 * Tests for {@see \ProxyManager\ProxyGenerator\AccessInterceptorScopeLocalizerGenerator} produced objects
 *
 * @group Functional
 * @coversNothing
 */
final class AccessInterceptorScopeLocalizerFunctionalTest extends TestCase
{
    /**
     * @param mixed[] $params
     * @param mixed   $expectedValue
     *
     * @dataProvider getProxyMethods
     */
    public function testMethodCalls(string $className, object $instance, string $method, array $params, $expectedValue
    ) : void
    {
        $proxyName = $this->generateProxy($className);

        /** @var AccessInterceptorInterface $proxy */
        $proxy = $proxyName::staticProxyConstructor($instance);

        $this->assertProxySynchronized($instance, $proxy);

        $callback = [$proxy, $method];

        self::assertIsCallable($callback);
        self::assertSame($expectedValue, $callback(...array_values($params)));

        /** @var callable&MockObject $listener */
        $listener = $this->getMockBuilder(stdClass::class)->setMethods(['__invoke'])->getMock();
        $listener
            ->expects(self::once())
            ->method('__invoke')
            ->with($proxy, $proxy, $method, $params, false);

        $proxy->setMethodPrefixInterceptor(
            $method,
            static function ($proxy, $instance, $method, $params, & $returnEarly) use ($listener) : void {
                $listener($proxy, $instance, $method, $params, $returnEarly);
            }
        );

        self::assertSame($expectedValue, $callback(...array_values($params)));

        $random = uniqid('', true);

        $proxy->setMethodPrefixInterceptor(
            $method,
            static function ($proxy, $instance, string $method, $params, & $returnEarly) use ($random) : string {
                $returnEarly = true;

                return $random;
            }
        );

        self::assertSame($random, $callback(...array_values($params)));

        $this->assertProxySynchronized($instance, $proxy);
    }

    /**
     * @param mixed[] $params
     * @param mixed   $expectedValue
     *
     * @dataProvider getProxyMethods
     */
    public function testMethodCallsWithSuffixListener(
        string $className,
        object $instance,
        string $method,
        array $params,
        $expectedValue
    ) : void {
        $proxyName = $this->generateProxy($className);

        /** @var AccessInterceptorInterface $proxy */
        $proxy    = $proxyName::staticProxyConstructor($instance);
        $callback = [$proxy, $method];

        self::assertIsCallable($callback);

        /** @var callable&MockObject $listener */
        $listener = $this->getMockBuilder(stdClass::class)->setMethods(['__invoke'])->getMock();
        $listener
            ->expects(self::once())
            ->method('__invoke')
            ->with($proxy, $proxy, $method, $params, $expectedValue, false);

        $proxy->setMethodSuffixInterceptor(
            $method,
            static function ($proxy, $instance, $method, $params, $returnValue, & $returnEarly) use ($listener) : void {
                $listener($proxy, $instance, $method, $params, $returnValue, $returnEarly);
            }
        );

        self::assertSame($expectedValue, $callback(...array_values($params)));

        $random = uniqid('', true);

        $proxy->setMethodSuffixInterceptor(
            $method,
            static function ($proxy, $instance, $method, $params, $returnValue, & $returnEarly) use ($random) : string {
                $returnEarly = true;

                return $random;
            }
        );

        self::assertSame($random, $callback(...array_values($params)));

        $this->assertProxySynchronized($instance, $proxy);
    }

    /**
     * @param mixed[] $params
     * @param mixed   $expectedValue
     *
     * @dataProvider getProxyMethods
     */
    public function testMethodCallsAfterUnSerialization(
        string $className,
        object $instance,
        string $method,
        array $params,
        $expectedValue
    ) : void {
        $proxyName = $this->generateProxy($className);
        /** @var AccessInterceptorInterface $proxy */
        $proxy = unserialize(serialize($proxyName::staticProxyConstructor($instance)));

        $callback = [$proxy, $method];

        self::assertIsCallable($callback);
        self::assertSame($expectedValue, $callback(...array_values($params)));
        $this->assertProxySynchronized($instance, $proxy);
    }

    /**
     * @param mixed[] $params
     * @param mixed   $expectedValue
     *
     * @dataProvider getProxyMethods
     */
    public function testMethodCallsAfterCloning(
        string $className,
        object $instance,
        string $method,
        array $params,
        $expectedValue
    ) : void {
        $proxyName = $this->generateProxy($className);

        /** @var AccessInterceptorInterface $proxy */
        $proxy    = $proxyName::staticProxyConstructor($instance);
        $cloned   = clone $proxy;
        $callback = [$cloned, $method];

        $this->assertProxySynchronized($instance, $proxy);
        self::assertIsCallable($callback);
        self::assertSame($expectedValue, $callback(...array_values($params)));
        $this->assertProxySynchronized($instance, $proxy);
    }

    /**
     * @param mixed $propertyValue
     *
     * @dataProvider getPropertyAccessProxies
     */
    public function testPropertyReadAccess(
        object $instance,
        AccessInterceptorInterface $proxy,
        string $publicProperty,
        $propertyValue
    ) : void {
        self::assertSame($propertyValue, $proxy->$publicProperty);
        $this->assertProxySynchronized($instance, $proxy);
    }

    /**
     * @dataProvider getPropertyAccessProxies
     */
    public function testPropertyWriteAccess(object $instance, AccessInterceptorInterface $proxy, string $publicProperty
    ) : void
    {
        $newValue               = uniqid('value', true);
        $proxy->$publicProperty = $newValue;

        self::assertSame($newValue, $proxy->$publicProperty);
        $this->assertProxySynchronized($instance, $proxy);
    }

    /**
     * @dataProvider getPropertyAccessProxies
     */
    public function testPropertyExistence(object $instance, AccessInterceptorInterface $proxy, string $publicProperty
    ) : void
    {
        self::assertSame(isset($instance->$publicProperty), isset($proxy->$publicProperty));
        $this->assertProxySynchronized($instance, $proxy);

        $instance->$publicProperty = null;
        self::assertFalse(isset($proxy->$publicProperty));
        $this->assertProxySynchronized($instance, $proxy);
    }

    /**
     * @dataProvider getPropertyAccessProxies
     */
    public function testPropertyUnset(object $instance, AccessInterceptorInterface $proxy, string $publicProperty
    ) : void
    {
        self::markTestSkipped('It is currently not possible to synchronize properties un-setting');
        unset($proxy->$publicProperty);

        self::assertFalse(isset($instance->$publicProperty));
        self::assertFalse(isset($proxy->$publicProperty));
        $this->assertProxySynchronized($instance, $proxy);
    }

    /**
     * Verifies that accessing a public property containing an array behaves like in a normal context
     */
    public function testCanWriteToArrayKeysInPublicProperty() : void
    {
        $instance  = new ClassWithPublicArrayProperty();
        $className = get_class($instance);
        $proxyName = $this->generateProxy($className);
        /** @var ClassWithPublicArrayProperty|AccessInterceptorInterface $proxy */
        $proxy = $proxyName::staticProxyConstructor($instance);

        self::assertInstanceOf(ClassWithPublicArrayProperty::class, $proxy);

        $proxy->arrayProperty['foo'] = 'bar';

        self::assertSame('bar', $proxy->arrayProperty['foo']);

        $proxy->arrayProperty = ['tab' => 'taz'];

        self::assertSame(['tab' => 'taz'], $proxy->arrayProperty);
        self::assertInstanceOf(AccessInterceptorInterface::class, $proxy);

        $this->assertProxySynchronized($instance, $proxy);
    }

    /**
     * Verifies that public properties retrieved via `__get` don't get modified in the object state
     */
    public function testWillNotModifyRetrievedPublicProperties() : void
    {
        $instance  = new ClassWithPublicProperties();
        $className = get_class($instance);
        $proxyName = $this->generateProxy($className);
        /** @var ClassWithPublicProperties|AccessInterceptorInterface $proxy */
        $proxy = $proxyName::staticProxyConstructor($instance);

        self::assertInstanceOf(ClassWithPublicProperties::class, $proxy);

        $variable = $proxy->property0;

        self::assertSame('property0', $variable);

        $variable = 'foo';

        self::assertSame('property0', $proxy->property0);

        self::assertInstanceOf(AccessInterceptorInterface::class, $proxy);

        $this->assertProxySynchronized($instance, $proxy);

        self::assertSame('foo', $variable);
    }

    /**
     * Verifies that public properties references retrieved via `__get` modify in the object state
     */
    public function testWillModifyByRefRetrievedPublicProperties() : void
    {
        $instance  = new ClassWithPublicProperties();
        $proxyName = $this->generateProxy(get_class($instance));
        /** @var ClassWithPublicProperties|AccessInterceptorInterface $proxy */
        $proxy = $proxyName::staticProxyConstructor($instance);

        self::assertInstanceOf(ClassWithPublicProperties::class, $proxy);

        $variable = &$proxy->property0;

        self::assertSame('property0', $variable);

        $variable = 'foo';

        self::assertSame('foo', $proxy->property0);

        self::assertInstanceOf(AccessInterceptorInterface::class, $proxy);

        $this->assertProxySynchronized($instance, $proxy);

        self::assertSame('foo', $variable);
    }

    /**
     * @group 115
     * @group 175
     */
    public function testWillBehaveLikeObjectWithNormalConstructor() : void
    {
        $instance = new ClassWithCounterConstructor(10);

        self::assertSame(10, $instance->amount, 'Verifying that test asset works as expected');
        self::assertSame(10, $instance->getAmount(), 'Verifying that test asset works as expected');
        $instance->__construct(3);
        self::assertSame(13, $instance->amount, 'Verifying that test asset works as expected');
        self::assertSame(13, $instance->getAmount(), 'Verifying that test asset works as expected');

        $proxyName = $this->generateProxy(get_class($instance));

        /** @var ClassWithCounterConstructor $proxy */
        $proxy = new $proxyName(15);

        self::assertSame(15, $proxy->amount, 'Verifying that the proxy constructor works as expected');
        self::assertSame(15, $proxy->getAmount(), 'Verifying that the proxy constructor works as expected');
        $proxy->__construct(5);
        self::assertSame(20, $proxy->amount, 'Verifying that the proxy constructor works as expected');
        self::assertSame(20, $proxy->getAmount(), 'Verifying that the proxy constructor works as expected');
    }

    /**
     * Generates a proxy for the given class name, and retrieves its class name
     *
     * @throws UnsupportedProxiedClassException
     */
    private function generateProxy(string $parentClassName) : string
    {
        $generatedClassName = __NAMESPACE__ . '\\' . UniqueIdentifierGenerator::getIdentifier('Foo');
        $generator          = new AccessInterceptorScopeLocalizerGenerator();
        $generatedClass     = new ClassGenerator($generatedClassName);
        $strategy           = new EvaluatingGeneratorStrategy();

        $generator->generate(new ReflectionClass($parentClassName), $generatedClass);
        $strategy->generate($generatedClass);

        return $generatedClassName;
    }

    /**
     * Generates a list of object | invoked method | parameters | expected result
     *
     * @return string[][]|object[][]|mixed[][][]
     */
    public static function getProxyMethods() : array
    {
        $selfHintParam = new ClassWithSelfHint();
        $empty         = new EmptyClass();

        return [
            [
                BaseClass::class,
                new BaseClass(),
                'publicMethod',
                [],
                'publicMethodDefault',
            ],
            [
                BaseClass::class,
                new BaseClass(),
                'publicTypeHintedMethod',
                ['param' => new stdClass()],
                'publicTypeHintedMethodDefault',
            ],
            [
                BaseClass::class,
                new BaseClass(),
                'publicByReferenceMethod',
                [],
                'publicByReferenceMethodDefault',
            ],
            [
                ClassWithSelfHint::class,
                new ClassWithSelfHint(),
                'selfHintMethod',
                ['parameter' => $selfHintParam],
                $selfHintParam,
            ],
            [
                ClassWithParentHint::class,
                new ClassWithParentHint(),
                'parentHintMethod',
                ['parameter' => $empty],
                $empty,
            ],
        ];
    }

    /**
     * Generates proxies and instances with a public property to feed to the property accessor methods
     *
     * @return object[][]|AccessInterceptorInterface[][]|string[][]
     */
    public function getPropertyAccessProxies() : array
    {
        $instance1  = new BaseClass();
        $proxyName1 = $this->generateProxy(get_class($instance1));

        return [
            [
                $instance1,
                $proxyName1::staticProxyConstructor($instance1),
                'publicProperty',
                'publicPropertyDefault',
            ],
        ];
    }

    private function assertProxySynchronized(object $instance, AccessInterceptorInterface $proxy) : void
    {
        $reflectionClass = new ReflectionClass($instance);

        foreach (Properties::fromReflectionClass($reflectionClass)->getInstanceProperties() as $property) {
            $property->setAccessible(true);

            self::assertSame(
                $property->getValue($instance),
                $property->getValue($proxy),
                'Property "' . $property->getName() . '" is synchronized between instance and proxy'
            );
        }
    }

    public function testWillForwardVariadicArguments() : void
    {
        $configuration = new Configuration();
        $factory       = new AccessInterceptorScopeLocalizerFactory($configuration);
        $targetObject  = new ClassWithMethodWithVariadicFunction();

        /** @var ClassWithMethodWithVariadicFunction $object */
        $object = $factory->createProxy(
            $targetObject,
            [
                static function () : string {
                    return 'Foo Baz';
                },
            ]
        );

        self::assertNull($object->bar);
        self::assertNull($object->baz);

        $object->foo('Ocramius', 'Malukenho', 'Danizord');
        self::assertSame('Ocramius', $object->bar);
        self::assertSame(['Malukenho', 'Danizord'], Assert::readAttribute($object, 'baz'));
    }

    /**
     * @group 265
     */
    public function testWillForwardVariadicByRefArguments() : void
    {
        $configuration = new Configuration();
        $factory       = new AccessInterceptorScopeLocalizerFactory($configuration);
        $targetObject  = new ClassWithMethodWithByRefVariadicFunction();

        /** @var ClassWithMethodWithByRefVariadicFunction $object */
        $object = $factory->createProxy(
            $targetObject,
            [
                static function () : string {
                    return 'Foo Baz';
                },
            ]
        );

        $parameters = ['a', 'b', 'c'];

        // first, testing normal variadic behavior (verifying we didn't screw up in the test asset)
        self::assertSame(['a', 'changed', 'c'], (new ClassWithMethodWithByRefVariadicFunction())->tuz(...$parameters));
        self::assertSame(['a', 'changed', 'c'], $object->tuz(...$parameters));
        self::assertSame(['a', 'changed', 'c'], $parameters, 'by-ref variadic parameter was changed');
    }

    /**
     * This test documents a known limitation: `func_get_args()` (and similar) don't work in proxied APIs.
     * If you manage to make this test pass, then please do send a patch
     *
     * @group 265
     */
    public function testWillNotForwardDynamicArguments() : void
    {
        /** @var ClassWithDynamicArgumentsMethod $object */
        $object = (new AccessInterceptorScopeLocalizerFactory())
            ->createProxy(
                new ClassWithDynamicArgumentsMethod(),
                [
                    'dynamicArgumentsMethod' => static function () : string {
                        return 'Foo Baz';
                    },
                ]
            );

        self::assertSame(['a', 'b'], (new ClassWithDynamicArgumentsMethod())->dynamicArgumentsMethod('a', 'b'));

        $this->expectException(ExpectationFailedException::class);

        self::assertSame(['a', 'b'], $object->dynamicArgumentsMethod('a', 'b'));
    }

    /**
     * @group 327
     */
    public function testWillInterceptAndReturnEarlyOnVoidMethod() : void
    {
        $skip      = random_int(100, 200);
        $addMore   = random_int(201, 300);
        $increment = random_int(301, 400);

        /** @var VoidCounter $object */
        $object = (new AccessInterceptorScopeLocalizerFactory())
            ->createProxy(
                new VoidCounter(),
                [
                    'increment' => static function (
                        VoidCounter $proxy,
                        VoidCounter $instance,
                        string $method,
                        array $params,
                        ?bool & $returnEarly
                    ) use ($skip) : void {
                        if ($skip !== $params['amount']) {
                            return;
                        }

                        $returnEarly = true;
                    },
                ],
                [
                    'increment' => static function (
                        VoidCounter $proxy,
                        VoidCounter $instance,
                        string $method,
                        array $params,
                        ?bool & $returnEarly
                    ) use ($addMore) : void {
                        if ($addMore !== $params['amount']) {
                            return;
                        }

                        $instance->counter += 1;
                    },
                ]
            );

        $object->increment($skip);
        self::assertSame(0, $object->counter);

        $object->increment($increment);
        self::assertSame($increment, $object->counter);

        $object->increment($addMore);
        self::assertSame($increment + $addMore + 1, $object->counter);
    }
}
