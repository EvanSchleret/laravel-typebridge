<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as Orchestra;
use EvanSchleret\LaravelTypeBridge\TypeBridgeServiceProvider;

abstract class TestCase extends Orchestra
{
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->cleanPath($this->defaultOutputPath());
        $this->cleanPath($this->alternateOutputPath());
    }

    protected function tearDown(): void
    {
        $this->cleanPath($this->defaultOutputPath());
        $this->cleanPath($this->alternateOutputPath());

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            TypeBridgeServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('typebridge.output.base_path', $this->defaultOutputPath());
        $app['config']->set('typebridge.output.additional_paths', []);
        $app['config']->set('typebridge.sources', [$this->fixturesPath('Resources/Default')]);
        $app['config']->set('typebridge.generation.use_semicolons', false);
        $app['config']->set('typebridge.generation.indent_size', 2);
        $app['config']->set('typebridge.generation.generate_index', true);
        $app['config']->set('typebridge.generation.shared_file', '_api');
        $app['config']->set('typebridge.generation.shared_append', []);
        $app['config']->set('typebridge.generation.append_templates', []);
        $app['config']->set('typebridge.files.extension', 'ts');
        $app['config']->set('typebridge.files.naming_pattern', '{name}');
    }

    protected function fixturesPath(string $suffix = ''): string
    {
        $base = __DIR__ . '/Fixtures';

        return $suffix === '' ? $base : $base . '/' . ltrim($suffix, '/');
    }

    protected function defaultOutputPath(): string
    {
        return __DIR__ . '/tmp/default-output';
    }

    protected function alternateOutputPath(): string
    {
        return __DIR__ . '/tmp/alternate-output';
    }

    private function cleanPath(string $path): void
    {
        if ($this->filesystem->isDirectory($path)) {
            $this->filesystem->deleteDirectory($path);
        }
    }
}
