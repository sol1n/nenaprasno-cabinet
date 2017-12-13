<?php

namespace App\Http\Middleware;

use Closure;
use App\Backend;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;

class SetLanguage
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $backend = app(Backend::class);
        $defaultLanguage = env('DEFAULT_LANGUAGE');
        $sessionLanguage = Cookie::get($backend->code . '-language');
        if ($sessionLanguage) {
            App::setLocale($sessionLanguage);
        } else {
            App::setLocale($defaultLanguage);
        }
        return $next($request);
    }
}
