<?php

declare(strict_types=1);

namespace EvanSchleret\LaravelTypeBridge\Generation;

final class DiscoveredResource
{
    public function __construct(
        public readonly string $className,
        public readonly string $name,
        public readonly array $structure,
        public readonly array $types,
        public readonly ?string $fileName,
        public readonly array $append,
        public readonly ?string $modelClass,
        public readonly ?string $aliasBase,
        public readonly ?string $aliasPlural,
    ) {
    }

    public function localTypeNames(): array
    {
        $names = [$this->name];

        foreach ($this->types as $key => $value) {
            if (is_string($key) && is_string($value) && $key !== '') {
                $names[] = $key;
            }
        }

        return array_values(array_unique($names));
    }
}
