<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Policy;
use App\Support\CompanyResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $company = CompanyResolver::forUser($user);

        // Si todavía no hay empresa seleccionada, muestra dashboard en ceros
        if (!$company) {
            return Inertia::render('App/Home', [
                'stats' => [
                    'monthPolicies' => 0,
                    'monthDebit' => 0,
                    'monthCredit' => 0,
                    'lastPolicies' => [],
                    'companyReady' => false,
                    'catalogReady' => false,
                    'offlineQueue' => 0,
                    'online' => true,
                ],
            ]);
        }

        $companyId = $company->id;

        // Rango del mes actual
        $start = now()->startOfMonth()->toDateString();
        $end   = now()->endOfMonth()->toDateString();

        // Query base con sumas por líneas
        $base = Policy::query()
            ->where('company_id', $companyId)
            ->withSum('lines as total_debit', 'debit')
            ->withSum('lines as total_credit', 'credit');

        // Pólizas del mes (conteo)
        $monthPolicies = (clone $base)
            ->whereBetween('movement_date', [$start, $end])
            ->count();

        // Totales del mes (sumando líneas)
        // Nota: SUM() en SQL sobre las sumas precalculadas de withSum no funciona directo en 1 query,
        // así que lo hacemos trayendo solo los totales del mes y sumando en PHP.
        $monthTotals = (clone $base)
            ->whereBetween('movement_date', [$start, $end])
            ->get(['id', 'total_debit', 'total_credit']);

        $monthDebit  = (float) $monthTotals->sum(fn ($p) => (float) ($p->total_debit ?? 0));
        $monthCredit = (float) $monthTotals->sum(fn ($p) => (float) ($p->total_credit ?? 0));

        // Actividad reciente (últimas 8 pólizas)
        $lastPolicies = (clone $base)
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'folio', 'policy_type', 'movement_date', 'status'])
            ->map(function ($p) {
                $status = $p->status ?? 'Borrador';

                return [
                    'id' => $p->id,
                    'folio' => $p->folio,
                    'policy_type' => $p->policy_type,
                    'movement_date' => $p->movement_date,
                    'total_debit' => (float) ($p->total_debit ?? 0),
                    'total_credit' => (float) ($p->total_credit ?? 0),

                    // Si quieres que en el dashboard diga "Pendiente" cuando sea Borrador:
                    'status_label' => $status === 'Borrador' ? 'Pendiente' : $status,
                    'status' => $status,
                ];
            })
            ->values();

        return Inertia::render('App/Home', [
            'stats' => [
                'monthPolicies' => $monthPolicies,
                'monthDebit' => $monthDebit,
                'monthCredit' => $monthCredit,
                'lastPolicies' => $lastPolicies,
                'companyReady' => true,
                'catalogReady' => true,
                'offlineQueue' => 0,
                'online' => true,
            ],
        ]);
    }
}