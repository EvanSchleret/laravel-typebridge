<?php

declare(strict_types=1);

namespace EvanSchleret\LaravelTypeBridge;

use Illuminate\Support\ServiceProvider;
use EvanSchleret\LaravelTypeBridge\Commands\CheckTypeBridgeCommand;
use EvanSchleret\LaravelTypeBridge\Commands\GenerateTypeBridgeCommand;

final class TypeBridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/typebridge.php',
            'typebridge',
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/typebridge.php' => config_path('typebridge.php'),
        ], 'typebridge-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateTypeBridgeCommand::class,
                CheckTypeBridgeCommand::class,
            ]);
        }
    }
}
