<?php

declare(strict_types=1);

namespace EvanSchleret\LaravelTypeBridge\Generation;

use ReflectionClass;

final class ModelResolver
{
    public function resolve(ReflectionClass $resourceReflection): ?string
    {
        $defaults = $resourceReflection->getDefaultProperties();
        if (array_key_exists('model', $defaults) && is_string($defaults['model']) && class_exists($defaults['model'])) {
            return $defaults['model'];
        }

        $shortName = $resourceReflection->getShortName();
        if (!str_ends_with($shortName, 'Resource')) {
            return null;
        }

        $baseName = substr($shortName, 0, -8);
        if ($baseName === '') {
            return null;
        }

        $conventionClass = "App\\Models\\{$baseName}";

        return class_exists($conventionClass) ? $conventionClass : null;
    }
}
