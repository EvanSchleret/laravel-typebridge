<?php

declare(strict_types=1);

namespace EvanSchleret\LaravelTypeBridge\Generation;

final class TypeParseResult
{
    public function __construct(
        public readonly string $normalizedType,
        public readonly array $references,
    ) {
    }
}
