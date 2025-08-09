<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->header('locale');
        if ($locale && array_key_exists($locale, config('languages'))) {
            App::setLocale($locale);
        } else {
            // Fallback to default locale if no preference is set or invalid
            App::setLocale(config('app.fallback_locale'));
        }

        return $next($request);
    }
}
