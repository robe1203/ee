<?php

namespace App\Http\Controllers;

use App\Models\Policy;
use App\Models\PolicyLine;
use App\Support\CompanyResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function policies(Request $request)
    {
        $company = CompanyResolver::forUser($request->user());

        $from = trim((string) $request->query('from', ''));
        $to   = trim((string) $request->query('to', ''));
        $type = trim((string) $request->query('type', ''));

        $policies = Policy::query()
            ->where('company_id', $company->id)
            ->withSum('lines as total_debit', 'debit')
            ->withSum('lines as total_credit', 'credit')
            ->when($from !== '', fn($q) => $q->whereDate('movement_date', '>=', $from))
            ->when($to !== '', fn($q) => $q->whereDate('movement_date', '<=', $to))
            ->when($type !== '', fn($q) => $q->where('policy_type', $type))
            ->orderByDesc('movement_date')
            ->limit(200)
            ->get();

        return Inertia::render('App/Reports/Policies', [
            'filters' => ['from' => $from, 'to' => $to, 'type' => $type],
            'items' => $policies->map(function ($p) {
                $date = $p->movement_date ? Carbon::parse($p->movement_date)->format('Y-m-d') : null;

                return [
                    'id' => $p->id,
                    'folio' => $p->folio,
                    'movement_date' => $date,
                    'policy_type' => $p->policy_type,
                    'status' => $p->status,
                    'total_debit' => (float) ($p->total_debit ?? 0),
                    'total_credit' => (float) ($p->total_credit ?? 0),
                ];
            })->values(),
        ]);
    }

    public function informe(Request $request)
    {
        $company = CompanyResolver::forUser($request->user());

        $from = trim((string) $request->query('from', ''));
        $to   = trim((string) $request->query('to', ''));

        $lines = PolicyLine::query()
            ->whereHas('policy', fn($q) => $q->where('company_id', $company->id))
            ->with([
                'policy:id,folio,policy_type,movement_date',
                'account:id,code,name',
            ])
            ->when($from !== '', fn($q) => $q->whereHas('policy', fn($p) => $p->whereDate('movement_date', '>=', $from)))
            ->when($to !== '', fn($q) => $q->whereHas('policy', fn($p) => $p->whereDate('movement_date', '<=', $to)))
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return Inertia::render('App/Reports/Informe', [
            'filters' => ['from' => $from, 'to' => $to],
            'items' => $lines->map(function ($l) {
                $date = $l->policy?->movement_date
                    ? Carbon::parse($l->policy->movement_date)->format('Y-m-d')
                    : null;

                return [
                    'date' => $date,
                    'folio' => $l->policy?->folio,
                    'type' => $l->policy?->policy_type,
                    'code' => $l->account?->code ?? $l->account_code,
                    'account' => $l->account?->name ?? $l->account_name,
                    'debit' => (float) ($l->debit ?? 0),
                    'credit' => (float) ($l->credit ?? 0),
                    'concept' => $l->concept,
                ];
            }),
        ]);
    }

    public function unified(Request $request)
    {
        $company = CompanyResolver::forUser($request->user());

        $folio = trim((string) $request->query('folio', ''));

        $policies = Policy::query()
            ->where('company_id', $company->id)
            ->withSum('lines as total_debit', 'debit')
            ->withSum('lines as total_credit', 'credit')
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $selectedPolicy = null;
        $lines = collect();

        if ($folio !== '') {
            $selectedPolicy = Policy::query()
                ->where('company_id', $company->id)
                ->where('folio', (int) $folio)
                ->withSum('lines as total_debit', 'debit')
                ->withSum('lines as total_credit', 'credit')
                ->first();

            if ($selectedPolicy) {
                $lines = PolicyLine::query()
                    ->where('policy_id', $selectedPolicy->id)
                    ->with(['account:id,code,name'])
                    ->orderBy('id')
                    ->get();
            }
        }

        return Inertia::render('App/Reports/Unified', [
            'selectedFolio' => $folio === '' ? null : (int) $folio,

            'policies' => $policies->map(function ($p) {
                $date = $p->movement_date
                    ? Carbon::parse($p->movement_date)->format('Y-m-d')
                    : null;

                return [
                    'id' => $p->id,
                    'fecha' => $date,
                    'folio' => $p->folio,
                    'policy_type' => $p->policy_type,
                    'status' => $this->normalizeStatusToUi($p->status),
                    'status_raw' => $p->status,
                    'total_debit' => (float) ($p->total_debit ?? 0),
                    'total_credit' => (float) ($p->total_credit ?? 0),
                ];
            })->values(),

            'policy' => $selectedPolicy ? [
                'id' => $selectedPolicy->id,
                'fecha' => $selectedPolicy->movement_date
                    ? Carbon::parse($selectedPolicy->movement_date)->format('Y-m-d')
                    : null,
                'folio' => $selectedPolicy->folio,
                'policy_type' => $selectedPolicy->policy_type,
                'status' => $this->normalizeStatusToUi($selectedPolicy->status),
                'status_raw' => $selectedPolicy->status,
                'total_debit' => (float) ($selectedPolicy->total_debit ?? 0),
                'total_credit' => (float) ($selectedPolicy->total_credit ?? 0),
            ] : null,

            'lines' => $lines->map(function ($l) use ($selectedPolicy) {
                $date = $selectedPolicy?->movement_date
                    ? Carbon::parse($selectedPolicy->movement_date)->format('Y-m-d')
                    : null;

                return [
                    'id' => $l->id,
                    'date' => $date,
                    'folio' => $selectedPolicy?->folio,
                    'type' => $selectedPolicy?->policy_type,
                    'code' => $l->account?->code ?? $l->account_code,
                    'account' => $l->account?->name ?? $l->account_name,
                    'debit' => (float) ($l->debit ?? 0),
                    'credit' => (float) ($l->credit ?? 0),
                    'concept' => $l->concept,
                ];
            })->values(),
        ]);
    }

    public function balanza(Request $request)
    {
        $user = $request->user();
        $company = CompanyResolver::forUser($user);
        $companyId = $company?->id;

        if (!$companyId) {
            return Inertia::render('App/Reports/Balanza', [
                'filters' => ['from' => '', 'to' => ''],
                'rows' => [],
                'totals' => [
                    'cargo' => 0,
                    'abono' => 0,
                    'deudor' => 0,
                    'acreedor' => 0,
                ],
                'company' => [
                    'name' => '',
                    'rfc' => '',
                    'regimen_codigo' => '',
                    'regimen_fiscal' => '',
                    'address' => '',
                ],
            ]);
        }

        $from = trim((string) $request->query('from', ''));
        $to   = trim((string) $request->query('to', ''));

        if ($from === '' || $to === '') {
            $from = now()->startOfMonth()->toDateString();
            $to   = now()->endOfMonth()->toDateString();
        }

        $lines = PolicyLine::query()
            ->whereHas('policy', function ($q) use ($companyId, $from, $to) {
                $q->where('company_id', $companyId)
                    ->whereDate('movement_date', '>=', $from)
                    ->whereDate('movement_date', '<=', $to);
            })
            ->selectRaw("
                MAX(account_code) as account_code,
                MAX(account_name) as account_name,
                SUM(debit) as debit_sum,
                SUM(credit) as credit_sum
            ")
            ->groupBy('account_code', 'account_name')
            ->orderBy('account_code')
            ->get();

        $rows = $lines->map(function ($l) {
            $cargo = (float) ($l->debit_sum ?? 0);
            $abono = (float) ($l->credit_sum ?? 0);
            $saldo = $cargo - $abono;

            return [
                'codigo'   => $l->account_code ?? '',
                'cuenta'   => $l->account_name ?? 'Sin nombre',
                'cargo'    => $cargo,
                'abono'    => $abono,
                'deudor'   => $saldo > 0 ? $saldo : 0,
                'acreedor' => $saldo < 0 ? abs($saldo) : 0,
            ];
        })->values();

        $totals = [
            'cargo'    => (float) $rows->sum('cargo'),
            'abono'    => (float) $rows->sum('abono'),
            'deudor'   => (float) $rows->sum('deudor'),
            'acreedor' => (float) $rows->sum('acreedor'),
        ];

        return Inertia::render('App/Reports/Balanza', [
            'filters' => ['from' => $from, 'to' => $to],
            'rows' => $rows,
            'totals' => $totals,
            'company' => [
                'name' => $company->name ?? '',
                'rfc' => $company->rfc ?? '',
                'regimen_codigo' => $company->regimen_codigo ?? '',
                'regimen_fiscal' => $company->regimen_fiscal ?? '',
                'address' => $company->address ?? '',
            ],
        ]);
    }

    public function libroDiario(Request $request)
    {
        $company = CompanyResolver::forUser($request->user());

        $from = trim((string) $request->query('from', ''));
        $to   = trim((string) $request->query('to', ''));

        $policies = Policy::query()
            ->where('company_id', $company->id)
            ->with(['lines'])
            ->when($from !== '', fn($q) => $q->whereDate('movement_date', '>=', $from))
            ->when($to !== '', fn($q) => $q->whereDate('movement_date', '<=', $to))
            ->orderBy('movement_date')
            ->orderBy('folio')
            ->limit(200)
            ->get()
            ->map(function ($p) {
                $date = $p->movement_date ? Carbon::parse($p->movement_date)->format('Y-m-d') : null;

                $lines = $p->lines->map(fn($l) => [
                    'account_code' => $l->account_code,
                    'account_name' => $l->account_name,
                    'concept' => $l->concept,
                    'debit' => (float) ($l->debit ?? 0),
                    'credit' => (float) ($l->credit ?? 0),
                ])->values();

                return [
                    'id' => $p->id,
                    'folio' => $p->folio,
                    'movement_date' => $date,
                    'policy_type' => $p->policy_type,
                    'status' => $p->status,
                    'total_debit' => (float) $lines->sum('debit'),
                    'total_credit' => (float) $lines->sum('credit'),
                    'lines' => $lines,
                ];
            });

        return Inertia::render('App/Reports/LibroDiario', [
            'filters' => ['from' => $from, 'to' => $to],
            'company' => [
                'name' => $company->name ?? '',
                'rfc' => $company->rfc ?? '',
                'regimen_codigo' => $company->regimen_codigo ?? '',
                'regimen_fiscal' => $company->regimen_fiscal ?? '',
                'address' => $company->address ?? '',
            ],
            'items' => $policies,
        ]);
    }

    public function balanceGeneral(Request $request)
    {
        $user = $request->user();
        $company = CompanyResolver::forUser($user);
        $companyId = $company?->id;

        $to = trim((string) $request->query('to', ''));

        if ($to === '') {
            $to = now()->toDateString();
        }

        if (!$companyId) {
            return Inertia::render('App/Reports/BalanceGeneral', [
                'filters' => ['to' => $to],
                'activos' => [],
                'pasivos' => [],
                'capital' => [],
                'totals' => [
                    'activos' => 0,
                    'pasivos' => 0,
                    'capital' => 0,
                    'pasivo_capital' => 0,
                ],
                'company' => [
                    'name' => '',
                    'rfc' => '',
                    'regimen_codigo' => '',
                    'regimen_fiscal' => '',
                    'address' => '',
                ],
            ]);
        }

        $balances = PolicyLine::query()
            ->whereHas('policy', function ($q) use ($companyId, $to) {
                $q->where('company_id', $companyId)
                    ->whereDate('movement_date', '<=', $to);
            })
            ->selectRaw("
                MAX(account_code) as account_code,
                MAX(account_name) as account_name,
                SUM(debit) as debit_sum,
                SUM(credit) as credit_sum
            ")
            ->groupBy('account_code', 'account_name')
            ->orderBy('account_code')
            ->get()
            ->map(function ($row) {
                $debit = (float) ($row->debit_sum ?? 0);
                $credit = (float) ($row->credit_sum ?? 0);
                $saldo = $debit - $credit;

                return [
                    'code' => $row->account_code ?? '',
                    'name' => $row->account_name ?? 'Sin nombre',
                    'saldo' => $saldo,
                ];
            })
            ->filter(fn($row) => abs((float) $row['saldo']) > 0.0001)
            ->values();

        $activos = $balances
            ->filter(fn($row) => str_starts_with((string) $row['code'], '1'))
            ->values()
            ->map(fn($row) => [
                'code' => $row['code'],
                'name' => $row['name'],
                'saldo' => (float) $row['saldo'],
            ]);

        $pasivos = $balances
            ->filter(fn($row) => str_starts_with((string) $row['code'], '2'))
            ->values()
            ->map(fn($row) => [
                'code' => $row['code'],
                'name' => $row['name'],
                'saldo' => abs((float) $row['saldo']),
            ]);

        $capital = $balances
            ->filter(fn($row) => str_starts_with((string) $row['code'], '3'))
            ->values()
            ->map(fn($row) => [
                'code' => $row['code'],
                'name' => $row['name'],
                'saldo' => abs((float) $row['saldo']),
            ]);

        $totals = [
            'activos' => (float) $activos->sum('saldo'),
            'pasivos' => (float) $pasivos->sum('saldo'),
            'capital' => (float) $capital->sum('saldo'),
        ];

        $totals['pasivo_capital'] = (float) ($totals['pasivos'] + $totals['capital']);

        return Inertia::render('App/Reports/BalanceGeneral', [
            'filters' => ['to' => $to],
            'activos' => $activos,
            'pasivos' => $pasivos,
            'capital' => $capital,
            'totals' => $totals,
            'company' => [
                'name' => $company->name ?? '',
                'rfc' => $company->rfc ?? '',
                'regimen_codigo' => $company->regimen_codigo ?? '',
                'regimen_fiscal' => $company->regimen_fiscal ?? '',
                'address' => $company->address ?? '',
            ],
        ]);
    }

    public function mayor(Request $request)
    {
        $company = CompanyResolver::forUser($request->user());

        $from = trim((string) $request->query('from', ''));
        $to   = trim((string) $request->query('to', ''));

        $lines = PolicyLine::query()
            ->whereHas('policy', fn($q) => $q->where('company_id', $company->id))
            ->with(['policy:id,folio,policy_type,movement_date'])
            ->when($from !== '', fn($q) => $q->whereHas('policy', fn($p) => $p->whereDate('movement_date', '>=', $from)))
            ->when($to !== '', fn($q) => $q->whereHas('policy', fn($p) => $p->whereDate('movement_date', '<=', $to)))
            ->orderBy('account_code')
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->map(function ($l) {
                $date = $l->policy?->movement_date ? Carbon::parse($l->policy->movement_date)->format('Y-m-d') : null;

                return [
                    'date' => $date,
                    'folio' => $l->policy?->folio,
                    'type' => $l->policy?->policy_type,
                    'code' => $l->account_code,
                    'account' => $l->account_name,
                    'debit' => (float) ($l->debit ?? 0),
                    'credit' => (float) ($l->credit ?? 0),
                    'concept' => $l->concept,
                ];
            });

        return Inertia::render('App/Reports/Mayor', [
            'filters' => ['from' => $from, 'to' => $to],
            'items' => $lines,
        ]);
    }

    public function estadoResultados(Request $request)
    {
        return Inertia::render('App/Reports/EstadoResultados');
    }

    private function normalizeStatusToUi(?string $status): string
    {
        return match ($status) {
            'locked' => 'Registrada',
            'draft'  => 'Borrador',
            default  => $status ?: 'Sin estado',
        };
    }
}