<?php

declare(strict_types=1);

namespace EvanSchleret\LaravelTypeBridge\Generation;

use Illuminate\Support\Str;

final class FileNameResolver
{
    public function resolve(
        string $resourceName,
        ?string $fileName,
        string $pattern,
        string $extension,
    ): string {
        if ($fileName !== null && trim($fileName) !== '') {
            return $this->ensureExtension(trim($fileName), $extension);
        }

        $resolvedPattern = strtr($pattern, [
            '{name}' => $resourceName,
            '{pascal}' => Str::studly($resourceName),
            '{camel}' => Str::camel($resourceName),
            '{snake}' => Str::snake($resourceName),
            '{kebab}' => Str::kebab($resourceName),
        ]);

        if ($resolvedPattern === '') {
            $resolvedPattern = $resourceName;
        }

        return $this->ensureExtension($resolvedPattern, $extension);
    }

    private function ensureExtension(string $fileName, string $extension): string
    {
        $cleanExtension = ltrim($extension, '.');
        if ($cleanExtension === '') {
            return $fileName;
        }

        if (Str::endsWith($fileName, ".{$cleanExtension}")) {
            return $fileName;
        }

        return "{$fileName}.{$cleanExtension}";
    }
}
