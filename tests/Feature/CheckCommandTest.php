<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('returns success when generated files are up to date', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/Default')]);

    Artisan::call('typebridge:generate');
    $exitCode = Artisan::call('typebridge:check');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('OK:');
});

it('returns failure when generated files are outdated', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/Default')]);

    Artisan::call('typebridge:generate');
    file_put_contents($this->defaultOutputPath() . '/User.ts', "changed\n");

    $exitCode = Artisan::call('typebridge:check');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Differences detected');
});

it('returns failure on stale files when clean is enabled', function (): void {
    config()->set('typebridge.sources', [$this->fixturesPath('Resources/Default')]);

    Artisan::call('typebridge:generate');
    file_put_contents($this->defaultOutputPath() . '/Stale.ts', "stale\n");

    $exitCode = Artisan::call('typebridge:check', [
        '--clean' => true,
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('- stale:');
});
