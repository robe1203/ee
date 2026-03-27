<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Policy;
use App\Support\CompanyResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PolicyController extends Controller
{
    private function activeCompany(Request $request)
    {
        return CompanyResolver::forUser($request->user());
    }

    private function companyIdFor(Request $request): int
    {
        return $this->activeCompany($request)->id;
    }

    private function policyTypes(): array
    {
        return ['Diario', 'Ingreso', 'Compras', 'Egreso', 'Nóminas'];
    }

    private function policyStatuses(): array
    {
        return ['Borrador', 'Registrada'];
    }

    private function normalizeStatusToDb(string $status): string
    {
        return match ($status) {
            'Registrada' => 'locked',
            default => 'draft',
        };
    }

    private function normalizeStatusToUi(?string $status): string
    {
        return match ($status) {
            'locked' => 'Registrada',
            'canceled' => 'Cancelada',
            default => 'Borrador',
        };
    }

    public function index(Request $request)
    {
        $company = $this->activeCompany($request);
        $companyId = $company->id;

        return Inertia::render('App/Policies/Index', [
            'currentCompanyUuid' => $company->uuid,
            'policies' => Policy::query()
                ->where('company_id', $companyId)
                ->orderByDesc('movement_date')
                ->orderByDesc('id')
                ->paginate(10)
                ->through(fn ($policy) => [
                    'id' => $policy->id,
                    'uuid' => $policy->uuid,
                    'folio' => $policy->folio,
                    'policy_type' => $policy->policy_type,
                    'movement_date' => is_string($policy->movement_date)
                        ? $policy->movement_date
                        : optional($policy->movement_date)->format('Y-m-d'),
                    'status' => $this->normalizeStatusToUi($policy->status),
                    'created_at' => $policy->created_at?->toIso8601String(),
                    'updated_at' => $policy->updated_at?->toIso8601String(),
                ]),
        ]);
    }

    public function create(Request $request)
    {
        $company = $this->activeCompany($request);
        $companyId = $company->id;
        $nextFolio = (int) (Policy::where('company_id', $companyId)->max('folio') ?? 999) + 1;

        return Inertia::render('App/Policies/Create', [
            'mode' => 'create',
            'policy' => null,
            'lines' => null,
            'currentCompanyUuid' => $company->uuid,
            'accounts' => Account::where('company_id', $companyId)
                ->orderBy('code')
                ->get(['id', 'uuid', 'code', 'name']),
            'nextFolio' => $nextFolio,
            'today' => now()->toDateString(),
            'policyTypes' => $this->policyTypes(),
            'policyStatuses' => $this->policyStatuses(),
        ]);
    }

    public function edit(Request $request, Policy $policy)
    {
        $company = $this->activeCompany($request);
        $companyId = $company->id;

        abort_unless((int) $policy->company_id === (int) $companyId, 403);

        $policy->load(['lines' => fn ($query) => $query->orderBy('sort')]);

        return Inertia::render('App/Policies/Create', [
            'mode' => 'edit',
            'currentCompanyUuid' => $company->uuid,
            'policy' => [
                'id' => $policy->id,
                'uuid' => $policy->uuid,
                'folio' => $policy->folio,
                'policy_type' => $policy->policy_type,
                'movement_date' => is_string($policy->movement_date)
                    ? $policy->movement_date
                    : optional($policy->movement_date)->format('Y-m-d'),
                'status' => $this->normalizeStatusToUi($policy->status),
                'created_at' => $policy->created_at?->toIso8601String(),
                'updated_at' => $policy->updated_at?->toIso8601String(),
            ],
            'lines' => $policy->lines->map(fn ($line) => [
                'id' => $line->id,
                'uuid' => $line->uuid,
                'account_id' => $line->account_id,
                'account_code' => $line->account_code,
                'account_name' => $line->account_name,
                'concept' => $line->concept,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'sort' => $line->sort,
            ]),
            'accounts' => Account::where('company_id', $companyId)
                ->orderBy('code')
                ->get(['id', 'uuid', 'code', 'name']),
            'nextFolio' => null,
            'today' => now()->toDateString(),
            'policyTypes' => $this->policyTypes(),
            'policyStatuses' => $this->policyStatuses(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->companyIdFor($request);

        $data = $request->validate([
            'folio' => ['required', 'integer', 'min:1'],
            'policy_type' => ['required', 'in:' . implode(',', $this->policyTypes())],
            'movement_date' => ['required', 'date'],
            'status' => ['required', 'in:' . implode(',', $this->policyStatuses())],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['nullable', 'integer'],
            'lines.*.account_code' => ['nullable', 'string', 'max:50'],
            'lines.*.account_name' => ['nullable', 'string', 'max:255'],
            'lines.*.concept' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
        ]);

        $totalDebit = collect($data['lines'])->sum(fn ($line) => (float) $line['debit']);
        $totalCredit = collect($data['lines'])->sum(fn ($line) => (float) $line['credit']);

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            return back()->withErrors(['lines' => 'La póliza no cuadra: cargos y abonos deben ser iguales.']);
        }

        $hasMovement = collect($data['lines'])->some(fn ($line) => ((float) $line['debit'] > 0) || ((float) $line['credit'] > 0));
        if (!$hasMovement) {
            return back()->withErrors(['lines' => 'Agrega al menos un cargo o abono mayor a 0.']);
        }

        return DB::transaction(function () use ($request, $companyId, $data) {
            $policy = Policy::create([
                'company_id' => $companyId,
                'user_id' => $request->user()->id,
                'folio' => $data['folio'],
                'policy_type' => $data['policy_type'],
                'movement_date' => $data['movement_date'],
                'status' => $this->normalizeStatusToDb($data['status']),
            ]);

            foreach ($data['lines'] as $index => $line) {
                $policy->lines()->create([
                    'uuid' => $line['uuid'] ?? null,
                    'account_id' => $line['account_id'] ?? null,
                    'account_code' => $line['account_code'] ?? null,
                    'account_name' => $line['account_name'] ?? null,
                    'concept' => $line['concept'] ?? null,
                    'debit' => (float) $line['debit'],
                    'credit' => (float) $line['credit'],
                    'sort' => $index + 1,
                ]);
            }

            return redirect()->route('app.policies.index')->with('success', 'Póliza guardada.');
        });
    }

    public function update(Request $request, Policy $policy)
    {
        $companyId = $this->companyIdFor($request);

        abort_unless((int) $policy->company_id === (int) $companyId, 403);

        $data = $request->validate([
            'folio' => ['required', 'integer', 'min:1'],
            'policy_type' => ['required', 'in:' . implode(',', $this->policyTypes())],
            'movement_date' => ['required', 'date'],
            'status' => ['required', 'in:' . implode(',', $this->policyStatuses())],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['nullable', 'integer'],
            'lines.*.account_code' => ['nullable', 'string', 'max:50'],
            'lines.*.account_name' => ['nullable', 'string', 'max:255'],
            'lines.*.concept' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
        ]);

        $totalDebit = collect($data['lines'])->sum(fn ($line) => (float) $line['debit']);
        $totalCredit = collect($data['lines'])->sum(fn ($line) => (float) $line['credit']);

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            return back()->withErrors(['lines' => 'La póliza no cuadra: cargos y abonos deben ser iguales.']);
        }

        $hasMovement = collect($data['lines'])->some(fn ($line) => ((float) $line['debit'] > 0) || ((float) $line['credit'] > 0));
        if (!$hasMovement) {
            return back()->withErrors(['lines' => 'Agrega al menos un cargo o abono mayor a 0.']);
        }

        return DB::transaction(function () use ($policy, $data) {
            $policy->update([
                'folio' => $data['folio'],
                'policy_type' => $data['policy_type'],
                'movement_date' => $data['movement_date'],
                'status' => $this->normalizeStatusToDb($data['status']),
            ]);

            $policy->lines()->delete();

            foreach ($data['lines'] as $index => $line) {
                $policy->lines()->create([
                    'uuid' => $line['uuid'] ?? null,
                    'account_id' => $line['account_id'] ?? null,
                    'account_code' => $line['account_code'] ?? null,
                    'account_name' => $line['account_name'] ?? null,
                    'concept' => $line['concept'] ?? null,
                    'debit' => (float) $line['debit'],
                    'credit' => (float) $line['credit'],
                    'sort' => $index + 1,
                ]);
            }

            return redirect()
                ->route('app.policies.edit', $policy->id)
                ->with('success', 'Póliza actualizada.');
        });
    }

    public function destroy(Request $request, Policy $policy)
    {
        $companyId = $this->companyIdFor($request);

        abort_unless((int) $policy->company_id === (int) $companyId, 403);

        if ($policy->status === 'locked') {
            return back()->withErrors([
                'error' => 'No puedes eliminar una póliza Registrada. Primero cámbiala a Cancelada o Borrador.',
            ]);
        }

        $policy->lines()->delete();
        $policy->delete();

        return redirect()->route('app.policies.index')->with('success', 'Póliza eliminada.');
    }
}
