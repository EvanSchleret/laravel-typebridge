<?php

declare(strict_types=1);

namespace EvanSchleret\LaravelTypeBridge\Commands;

use EvanSchleret\LaravelTypeBridge\Generation\TypeScriptGenerator;
use Illuminate\Console\Command;

final class CheckTypeBridgeCommand extends Command
{
    protected $signature = 'typebridge:check {--output-path=} {--clean} {--only=} {--except=} {--with-additional-paths}';

    protected $description = 'Check whether generated TypeScript files are up to date';

    public function __construct(
        private readonly TypeScriptGenerator $generator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $clean = (bool) $this->option('clean');
        $only = $this->parseListOption((string) $this->option('only'));
        $except = $this->parseListOption((string) $this->option('except'));
        $outputPathOverride = $this->option('output-path');
        $targetOutputPaths = $this->resolveTargetOutputPaths(
            is_string($outputPathOverride) ? $outputPathOverride : null,
            (bool) $this->option('with-additional-paths'),
        );

        $hasDifferences = false;

        foreach ($targetOutputPaths as $targetOutputPath) {
            $result = $this->generator->generate(
                outputPathOverride: $targetOutputPath,
                dryRun: true,
                clean: $clean,
                only: $only,
                except: $except,
            );

            $outdatedFiles = [];

            foreach ($result->files as $file) {
                if (!is_file($file->path)) {
                    $outdatedFiles[] = $file->path;
                    continue;
                }

                $currentContent = file_get_contents($file->path);
                if (!is_string($currentContent) || $currentContent !== $file->content) {
                    $outdatedFiles[] = $file->path;
                }
            }

            if ($outdatedFiles !== [] || ($clean && $result->deletedFiles !== [])) {
                $hasDifferences = true;
                $this->error("Differences detected in {$result->outputPath}");

                foreach ($outdatedFiles as $path) {
                    $this->line("- outdated: {$path}");
                }

                if ($clean) {
                    foreach ($result->deletedFiles as $path) {
                        $this->line("- stale: {$path}");
                    }
                }

                continue;
            }

            $this->info("OK: {$result->outputPath}");
        }

        if ($hasDifferences) {
            $this->line('Run typebridge:generate to update files');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function parseListOption(?string $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $parts = preg_split('/[,\s]+/', trim($raw));
        if (!is_array($parts)) {
            return [];
        }

        return array_values(array_filter($parts, static fn (string $value): bool => $value !== ''));
    }

    private function resolveTargetOutputPaths(?string $outputPathOverride, bool $withAdditionalPaths): array
    {
        if ($outputPathOverride !== null && trim($outputPathOverride) !== '') {
            $paths = [$outputPathOverride];
            if (!$withAdditionalPaths) {
                return $paths;
            }

            return array_values(array_unique(array_merge(
                $paths,
                $this->resolveAdditionalOutputPaths(),
            )));
        }

        return array_values(array_unique(array_merge(
            [null],
            $this->resolveAdditionalOutputPaths(),
        )));
    }

    private function resolveAdditionalOutputPaths(): array
    {
        $additionalPaths = config('typebridge.output.additional_paths', []);
        if (!is_array($additionalPaths)) {
            return [];
        }

        return array_values(array_filter(
            $additionalPaths,
            static fn (mixed $path): bool => is_string($path) && trim($path) !== '',
        ));
    }
}
