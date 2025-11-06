<?php

namespace App\Providers;

use App\Services\DWP\CycleStateMachine;
use App\Services\DWP\DwpDataService;
use App\Services\DWP\DwpPollingConfig;
use App\Services\DWP\ModbusService;
use App\Services\DWP\WaveformNormalizer;
use Illuminate\Support\ServiceProvider;

class DwpServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register configuration as singleton
        $this->app->singleton(DwpPollingConfig::class, function ($app) {
            return new DwpPollingConfig();
        });

        // Register WaveformNormalizer
        $this->app->bind(WaveformNormalizer::class, function ($app) {
            return new WaveformNormalizer(
                targetLength: DwpPollingConfig::NORMALIZED_WAVEFORM_LENGTH
            );
        });

        // Register ModbusService with config dependency
        $this->app->bind(ModbusService::class, function ($app) {
            return new ModbusService(
                config: $app->make(DwpPollingConfig::class)
            );
        });

        // Register CycleStateMachine with config dependency
        $this->app->bind(CycleStateMachine::class, function ($app) {
            return new CycleStateMachine(
                config: $app->make(DwpPollingConfig::class)
            );
        });

        // Register DwpDataService with waveform normalizer dependency
        $this->app->bind(DwpDataService::class, function ($app) {
            return new DwpDataService(
                waveformNormalizer: $app->make(WaveformNormalizer::class)
            );
        });

        // Register the main command with all dependencies
        $this->app->when(\App\Console\Commands\InsDwpPollRefactored::class)
            ->needs(ModbusService::class)
            ->give(function ($app) {
                return $app->make(ModbusService::class);
            });

        $this->app->when(\App\Console\Commands\InsDwpPollRefactored::class)
            ->needs(CycleStateMachine::class)
            ->give(function ($app) {
                return $app->make(CycleStateMachine::class);
            });

        $this->app->when(\App\Console\Commands\InsDwpPollRefactored::class)
            ->needs(DwpDataService::class)
            ->give(function ($app) {
                return $app->make(DwpDataService::class);
            });

        $this->app->when(\App\Console\Commands\InsDwpPollRefactored::class)
            ->needs(DwpPollingConfig::class)
            ->give(function ($app) {
                return $app->make(DwpPollingConfig::class);
            });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register any additional bootstrapping logic here
        // For example, you might want to validate configuration
        // or set up event listeners

        if ($this->app->runningInConsole()) {
            // Publish configuration files if needed
            // $this->publishes([
            //     __DIR__ . '/../../config/dwp.php' => config_path('dwp.php'),
            // ], 'dwp-config');
        }

        // You can add event listeners here if needed
        // Event::listen(CycleCompleted::class, CycleCompletedListener::class);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            DwpPollingConfig::class,
            WaveformNormalizer::class,
            ModbusService::class,
            CycleStateMachine::class,
            DwpDataService::class,
        ];
    }
}
