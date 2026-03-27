<?php

namespace App\Http\Middleware;

use App\Support\CompanyResolver;
use Closure;
use Illuminate\Http\Request;

class EnsureCompanySelected
{
    public function handle(Request $request, Closure $next)
    {
        $company = CompanyResolver::forUser($request->user());

        if (!$company) {
            $allowed =
                $request->routeIs('app.company.index') ||
                $request->routeIs('app.company.update') ||
                $request->routeIs('app.companies.*');

            if (!$allowed) {
                return redirect()
                    ->route('app.company.index')
                    ->with('error', 'Primero crea una empresa y selecciónala para continuar.');
            }
        }

        return $next($request);
    }
}
