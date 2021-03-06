<?php

declare(strict_types=1);

namespace ProxyManagerTest\ProxyGenerator\Util;

use PHPUnit\Framework\TestCase;
use ProxyManager\ProxyGenerator\Util\Properties;
use ProxyManagerTestAsset\ClassWithAbstractProtectedMethod;
use ProxyManagerTestAsset\ClassWithAbstractPublicMethod;
use ProxyManagerTestAsset\ClassWithCollidingPrivateInheritedProperties;
use ProxyManagerTestAsset\ClassWithMixedProperties;
use ProxyManagerTestAsset\ClassWithMixedReferenceableTypedProperties;
use ProxyManagerTestAsset\ClassWithMixedTypedProperties;
use ProxyManagerTestAsset\ClassWithPrivateProperties;
use ReflectionClass;
use ReflectionProperty;
use function array_map;
use function array_values;

/**
 * Tests for {@see \ProxyManager\ProxyGenerator\Util\Properties}
 *
 * @covers \ProxyManager\ProxyGenerator\Util\Properties
 * @group Coverage
 */
final class PropertiesTest extends TestCase
{
    public function testGetPublicProperties() : void
    {
        $properties       = Properties::fromReflectionClass(new ReflectionClass(ClassWithMixedProperties::class));
        $publicProperties = $properties->getPublicProperties();

        self::assertCount(3, $publicProperties);
        self::assertInstanceOf(ReflectionProperty::class, $publicProperties['publicProperty0']);
        self::assertInstanceOf(ReflectionProperty::class, $publicProperties['publicProperty1']);
        self::assertInstanceOf(ReflectionProperty::class, $publicProperties['publicProperty2']);
    }

    public function testGetPublicPropertiesSkipsAbstractMethods() : void
    {
        $properties = Properties::fromReflectionClass(new ReflectionClass(ClassWithAbstractPublicMethod::class));

        self::assertEmpty($properties->getPublicProperties());
    }

    public function testGetProtectedProperties() : void
    {
        $properties = Properties::fromReflectionClass(new ReflectionClass(ClassWithMixedProperties::class));

        $protectedProperties = $properties->getProtectedProperties();

        self::assertCount(3, $protectedProperties);

        self::assertInstanceOf(ReflectionProperty::class, $protectedProperties["\0*\0protectedProperty0"]);
        self::assertInstanceOf(ReflectionProperty::class, $protectedProperties["\0*\0protectedProperty1"]);
        self::assertInstanceOf(ReflectionProperty::class, $protectedProperties["\0*\0protectedProperty2"]);
    }

    public function testOnlyNullableProperties() : void
    {
        $nullablePublicProperties = Properties::fromReflectionClass(new ReflectionClass(ClassWithMixedTypedProperties::class))
            ->onlyNullableProperties()
            ->getInstanceProperties();

        self::assertCount(48, $nullablePublicProperties);
        self::assertSame(
            [
                'publicUnTypedProperty',
                'publicUnTypedPropertyWithoutDefaultValue',
                'publicNullableBoolProperty',
                'publicNullableBoolPropertyWithoutDefaultValue',
                'publicNullableIntProperty',
                'publicNullableIntPropertyWithoutDefaultValue',
                'publicNullableFloatProperty',
                'publicNullableFloatPropertyWithoutDefaultValue',
                'publicNullableStringProperty',
                'publicNullableStringPropertyWithoutDefaultValue',
                'publicNullableArrayProperty',
                'publicNullableArrayPropertyWithoutDefaultValue',
                'publicNullableIterableProperty',
                'publicNullableIterablePropertyWithoutDefaultValue',
                'publicNullableObjectProperty',
                'publicNullableClassProperty',
                'protectedUnTypedProperty',
                'protectedUnTypedPropertyWithoutDefaultValue',
                'protectedNullableBoolProperty',
                'protectedNullableBoolPropertyWithoutDefaultValue',
                'protectedNullableIntProperty',
                'protectedNullableIntPropertyWithoutDefaultValue',
                'protectedNullableFloatProperty',
                'protectedNullableFloatPropertyWithoutDefaultValue',
                'protectedNullableStringProperty',
                'protectedNullableStringPropertyWithoutDefaultValue',
                'protectedNullableArrayProperty',
                'protectedNullableArrayPropertyWithoutDefaultValue',
                'protectedNullableIterableProperty',
                'protectedNullableIterablePropertyWithoutDefaultValue',
                'protectedNullableObjectProperty',
                'protectedNullableClassProperty',
                'privateUnTypedProperty',
                'privateUnTypedPropertyWithoutDefaultValue',
                'privateNullableBoolProperty',
                'privateNullableBoolPropertyWithoutDefaultValue',
                'privateNullableIntProperty',
                'privateNullableIntPropertyWithoutDefaultValue',
                'privateNullableFloatProperty',
                'privateNullableFloatPropertyWithoutDefaultValue',
                'privateNullableStringProperty',
                'privateNullableStringPropertyWithoutDefaultValue',
                'privateNullableArrayProperty',
                'privateNullableArrayPropertyWithoutDefaultValue',
                'privateNullableIterableProperty',
                'privateNullableIterablePropertyWithoutDefaultValue',
                'privateNullableObjectProperty',
                'privateNullableClassProperty',
            ],
            array_values(array_map(static function (ReflectionProperty $property) : string {
                return $property->getName();
            }, $nullablePublicProperties))
        );
    }

    public function testOnlyPropertiesThatCanBeUnset() : void
    {
        $nonReferenceableProperties = Properties::fromReflectionClass(new ReflectionClass(ClassWithMixedTypedProperties::class))
            ->onlyPropertiesThatCanBeUnset()
            ->getInstanceProperties();

        self::assertCount(42, $nonReferenceableProperties);
        self::assertSame(
            [
                'publicUnTypedProperty',
                'publicUnTypedPropertyWithoutDefaultValue',
                'publicBoolProperty',
                'publicNullableBoolProperty',
                'publicIntProperty',
                'publicNullableIntProperty',
                'publicFloatProperty',
                'publicNullableFloatProperty',
                'publicStringProperty',
                'publicNullableStringProperty',
                'publicArrayProperty',
                'publicNullableArrayProperty',
                'publicIterableProperty',
                'publicNullableIterableProperty',
                'protectedUnTypedProperty',
                'protectedUnTypedPropertyWithoutDefaultValue',
                'protectedBoolProperty',
                'protectedNullableBoolProperty',
                'protectedIntProperty',
                'protectedNullableIntProperty',
                'protectedFloatProperty',
                'protectedNullableFloatProperty',
                'protectedStringProperty',
                'protectedNullableStringProperty',
                'protectedArrayProperty',
                'protectedNullableArrayProperty',
                'protectedIterableProperty',
                'protectedNullableIterableProperty',
                'privateUnTypedProperty',
                'privateUnTypedPropertyWithoutDefaultValue',
                'privateBoolProperty',
                'privateNullableBoolProperty',
                'privateIntProperty',
                'privateNullableIntProperty',
                'privateFloatProperty',
                'privateNullableFloatProperty',
                'privateStringProperty',
                'privateNullableStringProperty',
                'privateArrayProperty',
                'privateNullableArrayProperty',
                'privateIterableProperty',
                'privateNullableIterableProperty',
            ],
            array_values(array_map(static function (ReflectionProperty $property) : string {
                return $property->getName();
            }, $nonReferenceableProperties))
        );
    }

    public function testOnlyNonReferenceableProperties() : void
    {
        self::assertTrue(
            Properties::fromReflectionClass(new ReflectionClass(ClassWithMixedReferenceableTypedProperties::class))
                ->onlyNonReferenceableProperties()
                ->empty()
        );

        $nonReferenceableProperties = Properties::fromReflectionClass(new ReflectionClass(ClassWithMixedTypedProperties::class))
            ->onlyNonReferenceableProperties()
            ->getInstanceProperties();

        self::assertCount(48, $nonReferenceableProperties);
        self::assertSame(
            [
                'publicBoolPropertyWithoutDefaultValue',
                'publicNullableBoolPropertyWithoutDefaultValue',
                'publicIntPropertyWithoutDefaultValue',
                'publicNullableIntPropertyWithoutDefaultValue',
                'publicFloatPropertyWithoutDefaultValue',
                'publicNullableFloatPropertyWithoutDefaultValue',
                'publicStringPropertyWithoutDefaultValue',
                'publicNullableStringPropertyWithoutDefaultValue',
                'publicArrayPropertyWithoutDefaultValue',
                'publicNullableArrayPropertyWithoutDefaultValue',
                'publicIterablePropertyWithoutDefaultValue',
                'publicNullableIterablePropertyWithoutDefaultValue',
                'publicObjectProperty',
                'publicNullableObjectProperty',
                'publicClassProperty',
                'publicNullableClassProperty',
                'protectedBoolPropertyWithoutDefaultValue',
                'protectedNullableBoolPropertyWithoutDefaultValue',
                'protectedIntPropertyWithoutDefaultValue',
                'protectedNullableIntPropertyWithoutDefaultValue',
                'protectedFloatPropertyWithoutDefaultValue',
                'protectedNullableFloatPropertyWithoutDefaultValue',
                'protectedStringPropertyWithoutDefaultValue',
                'protectedNullableStringPropertyWithoutDefaultValue',
                'protectedArrayPropertyWithoutDefaultValue',
                'protectedNullableArrayPropertyWithoutDefaultValue',
                'protectedIterablePropertyWithoutDefaultValue',
                'protectedNullableIterablePropertyWithoutDefaultValue',
                'protectedObjectProperty',
                'protectedNullableObjectProperty',
                'protectedClassProperty',
                'protectedNullableClassProperty',
                'privateBoolPropertyWithoutDefaultValue',
                'privateNullableBoolPropertyWithoutDefaultValue',
                'privateIntPropertyWithoutDefaultValue',
                'privateNullableIntPropertyWithoutDefaultValue',
                'privateFloatPropertyWithoutDefaultValue',
                'privateNullableFloatPropertyWithoutDefaultValue',
                'privateStringPropertyWithoutDefaultValue',
                'privateNullableStringPropertyWithoutDefaultValue',
                'privateArrayPropertyWithoutDefaultValue',
                'privateNullableArrayPropertyWithoutDefaultValue',
                'privateIterablePropertyWithoutDefaultValue',
                'privateNullableIterablePropertyWithoutDefaultValue',
                'privateObjectProperty',
                'privateNullableObjectProperty',
                'privateClassProperty',
                'privateNullableClassProperty',
            ],
            array_values(array_map(static function (ReflectionProperty $property) : string {
                return $property->getName();
            }, $nonReferenceableProperties))
        );
    }

    public function testGetProtectedPropertiesSkipsAbstractMethods() : void
    {
        $properties = Properties::fromReflectionClass(new ReflectionClass(ClassWithAbstractProtectedMethod::class));

        self::assertEmpty($properties->getProtectedProperties());
    }

    public function testGetPrivateProperties() : void
    {
        $properties = Properties::fromReflectionClass(new ReflectionClass(ClassWithMixedProperties::class));

        $privateProperties = $properties->getPrivateProperties();

        self::assertCount(3, $privateProperties);

        $prefix = "\0" . ClassWithMixedProperties::class . "\0";

        self::assertInstanceOf(ReflectionProperty::class, $privateProperties[$prefix . 'privateProperty0']);
        self::assertInstanceOf(ReflectionProperty::class, $privateProperties[$prefix . 'privateProperty1']);
        self::assertInstanceOf(ReflectionProperty::class, $privateProperties[$prefix . 'privateProperty2']);
    }

    public function testGetPrivatePropertiesFromInheritance() : void
    {
        $properties = Properties::fromReflectionClass(
            new ReflectionClass(ClassWithCollidingPrivateInheritedProperties::class)
        );

        $privateProperties = $properties->getPrivateProperties();

        self::assertCount(11, $privateProperties);

        $prefix = "\0" . ClassWithCollidingPrivateInheritedProperties::class . "\0";

        self::assertInstanceOf(ReflectionProperty::class, $privateProperties[$prefix . 'property0']);

        $prefix = "\0" . ClassWithPrivateProperties::class . "\0";

        self::assertInstanceOf(ReflectionProperty::class, $privateProperties[$prefix . 'property0']);
        self::assertInstanceOf(ReflectionProperty::class, $privateProperties[$prefix . 'property1']);
        self::assertInstanceOf(ReflectionProperty::class, $privateProperties[$prefix . 'property2']);
        self::assertInstanceOf(ReflectionProperty::class, $privateProperties[$prefix . 'property3']);
        self::assertInstanceOf(ReflectionProperty::class, $privateProperties[$prefix . 'property4']);
        self::assertInstanceOf(ReflectionProperty::class, $privateProperties[$prefix . 'property5']);
        self::assertInstanceOf(ReflectionProperty::class, $privateProperties[$prefix . 'property6']);
        self::assertInstanceOf(ReflectionProperty::class, $privateProperties[$prefix . 'property7']);
        self::assertInstanceOf(ReflectionProperty::class, $privateProperties[$prefix . 'property8']);
        self::assertInstanceOf(ReflectionProperty::class, $privateProperties[$prefix . 'property9']);
    }

    public function testGetAccessibleMethods() : void
    {
        $properties           = Properties::fromReflectionClass(new ReflectionClass(ClassWithMixedProperties::class));
        $accessibleProperties = $properties->getAccessibleProperties();

        self::assertCount(6, $accessibleProperties);
        self::assertInstanceOf(ReflectionProperty::class, $accessibleProperties['publicProperty0']);
        self::assertInstanceOf(ReflectionProperty::class, $accessibleProperties['publicProperty1']);
        self::assertInstanceOf(ReflectionProperty::class, $accessibleProperties['publicProperty2']);
        self::assertInstanceOf(ReflectionProperty::class, $accessibleProperties["\0*\0protectedProperty0"]);
        self::assertInstanceOf(ReflectionProperty::class, $accessibleProperties["\0*\0protectedProperty1"]);
        self::assertInstanceOf(ReflectionProperty::class, $accessibleProperties["\0*\0protectedProperty2"]);
    }

    public function testGetGroupedPrivateProperties() : void
    {
        $properties     = Properties::fromReflectionClass(new ReflectionClass(ClassWithMixedProperties::class));
        $groupedPrivate = $properties->getGroupedPrivateProperties();

        self::assertCount(1, $groupedPrivate);

        $group = $groupedPrivate[ClassWithMixedProperties::class];

        self::assertCount(3, $group);

        self::assertInstanceOf(ReflectionProperty::class, $group['privateProperty0']);
        self::assertInstanceOf(ReflectionProperty::class, $group['privateProperty1']);
        self::assertInstanceOf(ReflectionProperty::class, $group['privateProperty2']);
    }

    public function testGetGroupedPrivatePropertiesWithInheritedProperties() : void
    {
        $properties = Properties::fromReflectionClass(
            new ReflectionClass(ClassWithCollidingPrivateInheritedProperties::class)
        );

        $groupedPrivate = $properties->getGroupedPrivateProperties();

        self::assertCount(2, $groupedPrivate);

        $group1 = $groupedPrivate[ClassWithCollidingPrivateInheritedProperties::class];
        $group2 = $groupedPrivate[ClassWithPrivateProperties::class];

        self::assertCount(1, $group1);
        self::assertCount(10, $group2);

        self::assertInstanceOf(ReflectionProperty::class, $group1['property0']);
        self::assertInstanceOf(ReflectionProperty::class, $group2['property0']);
        self::assertInstanceOf(ReflectionProperty::class, $group2['property1']);
        self::assertInstanceOf(ReflectionProperty::class, $group2['property2']);
        self::assertInstanceOf(ReflectionProperty::class, $group2['property3']);
        self::assertInstanceOf(ReflectionProperty::class, $group2['property4']);
        self::assertInstanceOf(ReflectionProperty::class, $group2['property5']);
        self::assertInstanceOf(ReflectionProperty::class, $group2['property6']);
        self::assertInstanceOf(ReflectionProperty::class, $group2['property7']);
        self::assertInstanceOf(ReflectionProperty::class, $group2['property8']);
        self::assertInstanceOf(ReflectionProperty::class, $group2['property9']);
    }

    public function testGetInstanceProperties() : void
    {
        $properties = Properties::fromReflectionClass(
            new ReflectionClass(ClassWithMixedProperties::class)
        );

        self::assertCount(9, $properties->getInstanceProperties());
    }

    /**
     * @param string $propertyName with property name
     *
     * @dataProvider propertiesToSkipFixture
     */
    public function testSkipPropertiesByFiltering(string $propertyName) : void
    {
        $properties = Properties::fromReflectionClass(
            new ReflectionClass(ClassWithMixedProperties::class)
        );

        self::assertArrayHasKey($propertyName, $properties->getInstanceProperties());
        $filteredProperties =  $properties->filter([$propertyName]);

        self::assertArrayNotHasKey($propertyName, $filteredProperties->getInstanceProperties());
    }

    public function testSkipOverwritedPropertyUsingInheritance() : void
    {
        $propertyName = "\0ProxyManagerTestAsset\\ClassWithCollidingPrivateInheritedProperties\0property0";

        $properties = Properties::fromReflectionClass(
            new ReflectionClass(ClassWithCollidingPrivateInheritedProperties::class)
        );

        self::assertArrayHasKey($propertyName, $properties->getInstanceProperties());
        $filteredProperties =  $properties->filter([$propertyName]);

        self::assertArrayNotHasKey($propertyName, $filteredProperties->getInstanceProperties());
    }

    public function testPropertiesIsSkippedFromRelatedMethods() : void
    {
        $properties = Properties::fromReflectionClass(
            new ReflectionClass(ClassWithMixedProperties::class)
        );

        self::assertArrayHasKey("\0*\0protectedProperty0", $properties->getProtectedProperties());
        self::assertArrayHasKey("\0*\0protectedProperty0", $properties->getInstanceProperties());
        $filteredProperties =  $properties->filter(["\0*\0protectedProperty0"]);

        self::assertArrayNotHasKey("\0*\0protectedProperty0", $filteredProperties->getProtectedProperties());
        self::assertArrayNotHasKey("\0*\0protectedProperty0", $filteredProperties->getInstanceProperties());
    }

    /** @return string[][] */
    public function propertiesToSkipFixture() : array
    {
        return [
            ['publicProperty0'],
            ["\0*\0protectedProperty0"],
            ["\0ProxyManagerTestAsset\\ClassWithMixedProperties\0privateProperty0"],
        ];
    }
}
