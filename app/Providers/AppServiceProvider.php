<?php

namespace App\Providers;

use App\Http\Requests\LoginRequest;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FortifyLoginRequest::class, LoginRequest::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}