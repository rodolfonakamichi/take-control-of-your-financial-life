<?php

namespace App\Providers;

use App\Infra\Tempo\Relogio;
use App\Infra\Tempo\RelogioDoSistema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Relogio::class, RelogioDoSistema::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
