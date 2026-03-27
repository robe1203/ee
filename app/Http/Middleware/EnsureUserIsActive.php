<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && !$request->user()->is_active) {
            auth()->logout();

            return redirect()
                ->route('login')
                ->with('status', 'Tu cuenta está desactivada. Contacta al administrador.');
        }

        return $next($request);
    }
}