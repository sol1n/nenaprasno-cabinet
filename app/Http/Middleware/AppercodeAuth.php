<?php

namespace App\Http\Middleware;

use Closure;
use App\User;
use App\Backend;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;

class AppercodeAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $backend = app(Backend::Class);
    
        if (! isset($backend->token)) {
            $request->session()->put(AuthController::REDIRECT_KEY, $request->path());
            return redirect('/login');
        }

        return $next($request);
    }
}
