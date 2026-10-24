<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Saat dibuka dari HP lewat proxy HTTPS (lan/start-lan.ps1), semua URL harus memakai https.
        $lanHttpsPort = (int) config('app.lan_https_port');

        if ($lanHttpsPort > 0 && ! $this->app->runningInConsole() && (int) $this->app['request']->getPort() === $lanHttpsPort) {
            URL::forceScheme('https');
        }
    }
}
