<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Auth::viaRequest('sanctum', function (Request $request) {
            $token = $request->bearerToken();

            if (! $token) {
                return null;
            }

            $accessToken = PersonalAccessToken::query()
                ->where('token', hash('sha256', $token))
                ->with('user')
                ->first();

            return $accessToken?->user;
        });
    }
}
