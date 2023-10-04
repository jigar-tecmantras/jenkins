<?php

namespace App\Providers;

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
        ini_set('memory_limit','-1');
        ini_set('max_execution_time','-1');
        ini_set('max_input_time','-1');
        ini_set('post_max_size','1025M');
        ini_set('upload_max_filesize','1024M');
    }
}
