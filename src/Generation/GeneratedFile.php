<?php

declare(strict_types=1);

namespace EvanSchleret\LaravelTypeBridge\Generation;

final class GeneratedFile
{
    public function __construct(
        public readonly string $path,
        public readonly string $content,
    ) {
    }
}
