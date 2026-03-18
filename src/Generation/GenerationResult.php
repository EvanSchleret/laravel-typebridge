<?php

declare(strict_types=1);

namespace EvanSchleret\LaravelTypeBridge\Generation;

final class GenerationResult
{
    public function __construct(
        public readonly string $outputPath,
        public readonly bool $dryRun,
        public readonly array $files,
    ) {
    }
}
