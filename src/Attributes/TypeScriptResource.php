<?php

declare(strict_types=1);

namespace EvanSchleret\LaravelTypeBridge\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class TypeScriptResource
{
    public function __construct(
        public readonly string $name,
        public readonly array $structure = [],
        public readonly array $types = [],
        public readonly ?string $fileName = null,
        public readonly array $append = [],
        public readonly ?string $aliasBase = null,
        public readonly ?string $aliasPlural = null,
    ) {
    }
}
