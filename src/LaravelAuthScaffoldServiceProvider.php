<?php

namespace TriQuang\LaravelAuthScaffold;

use Illuminate\Support\ServiceProvider;
use TriQuang\LaravelAuthScaffold\Commands\MakeAuthScaffoldCommand;

class LaravelAuthScaffoldServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            // Register the command
            $this->commands([
                MakeAuthScaffoldCommand::class,
            ]);

            // Publish stubs for customization
            $this->publishes([
                __DIR__ . '/../stubs' => base_path('stubs/vendor/triquang/laravel-auth-scaffold'),
            ], 'auth-scaffold-stubs');
        }
    }

    public function register() {}
}
