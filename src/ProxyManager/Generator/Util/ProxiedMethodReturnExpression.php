<?php

declare(strict_types=1);

namespace ProxyManager\Generator\Util;

use ReflectionMethod;

/**
 * Utility class to generate return expressions in method, given a method signature.
 *
 * This is required since return expressions may be forbidden by the method signature (void).
 */
final class ProxiedMethodReturnExpression
{
    public static function generate(string $returnedValueExpression, ?ReflectionMethod $originalMethod) : string
    {
        if ($originalMethod && (string) $originalMethod->getReturnType() === 'void') {
            return $returnedValueExpression . ";\nreturn;";
        }

        return 'return ' . $returnedValueExpression . ';';
    }
}
