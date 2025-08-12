<?php

namespace App\Providers;

use Debugbar;
use File;
use Illuminate\Support\ServiceProvider;

class LogDebugBarProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $logFile = storage_path('logs/laravel.log');
        if (File::exists($logFile))
        {
            $logContent = File::get($logFile);


            Debugbar::addMessage('Logs de laravel.log', 'log');
            Debugbar::addMessage($logContent, 'log');
        }
    }
}
