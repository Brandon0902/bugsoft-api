<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\PersonalAccessToken;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Usa el modelo oficial de Sanctum para personal_access_tokens
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}