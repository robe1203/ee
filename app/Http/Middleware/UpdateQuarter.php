<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class UpdateQuarter
{
    /**
     * Incrementa automáticamente el cuatrimestre (quarter) cada 4 meses
     * para usuarios con rol "alumno".
     *
     * - Si no existe quarter_next_at, se inicializa.
     * - Si ya pasó la fecha, incrementa tantas veces como sea necesario.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('alumno')) {

            // Solo si el alumno tiene quarter asignado (si está null, lo respetamos)
            if (!is_null($user->quarter)) {

                // Inicialización
                if (!$user->quarter_started_at) {
                    $user->quarter_started_at = $user->created_at ?? now();
                }

                if (!$user->quarter_next_at) {
                    $user->quarter_next_at = $user->quarter_started_at->copy()->addMonthsNoOverflow(4);
                }

                // Actualizar si ya toca
                $now = now();

                if ($user->quarter_next_at && $now->greaterThanOrEqualTo($user->quarter_next_at)) {
                    $started = $user->quarter_started_at->copy();
                    $nextAt  = $user->quarter_next_at->copy();
                    $q       = (int) $user->quarter;

                    // Incrementa en bloques de 4 meses (por si pasó mucho tiempo)
                    while ($now->greaterThanOrEqualTo($nextAt)) {
                        $q++;
                        $started = $nextAt->copy();                 // nuevo inicio
                        $nextAt  = $nextAt->copy()->addMonthsNoOverflow(4);

                        // Límite por seguridad
                        if ($q > 20) {
                            break;
                        }
                    }

                    $user->quarter = $q;
                    $user->quarter_started_at = $started;
                    $user->quarter_next_at = $nextAt;
                    $user->save();
                }
            }
        }

        return $next($request);
    }
}