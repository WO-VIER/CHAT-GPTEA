<?php

namespace App\Providers;

use App\Services\ChatService;
use App\Services\ConversationService;
use Barryvdh\Debugbar\Middleware\DebugbarEnabled;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('Debugbar', \Barryvdh\Debugbar\Facades\Debugbar::class);

        $this->app->singleton(ConversationService::class, function($app)
        {
            return new ConversationService($app->make(ChatService::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Debugbar::enable();
    }
}
