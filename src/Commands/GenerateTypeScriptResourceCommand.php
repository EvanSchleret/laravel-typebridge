<?php

declare(strict_types=1);

namespace EvanSchleret\LaravelTypeBridge\Commands;

use Illuminate\Console\Command;
use EvanSchleret\LaravelTypeBridge\Generation\TypeScriptGenerator;

final class GenerateTypeScriptResourceCommand extends Command
{
    protected $signature = 'resource-typescript:generate {--output-path=} {--dry-run}';

    protected $description = 'Generate TypeScript files from #[TypeScriptResource] attributes';

    public function __construct(
        private readonly TypeScriptGenerator $generator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->generator->generate(
            outputPathOverride: $this->option('output-path'),
            dryRun: (bool) $this->option('dry-run'),
        );

        if ($result->dryRun) {
            $this->line('Dry run mode: ' . count($result->files) . " file(s) would be generated in {$result->outputPath}");

            foreach ($result->files as $file) {
                $this->line('');
                $this->line("=== {$file->path} ===");
                $this->line($file->content);
            }

            return self::SUCCESS;
        }

        $this->info('Generated ' . count($result->files) . " file(s) in {$result->outputPath}");

        return self::SUCCESS;
    }
}
