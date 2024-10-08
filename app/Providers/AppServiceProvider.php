<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

use TCG\Voyager\Facades\Voyager;

use App\Actions\TranslatedVideoView;
use App\Actions\TranslatedVideoDownload;

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
        Schema::defaultStringLength(191);

        if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&  $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
            \URL::forceScheme('https');
        }

        // Videos actions
        Voyager::addAction(TranslatedVideoView::class);
        Voyager::addAction(TranslatedVideoDownload::class);
    }
}
