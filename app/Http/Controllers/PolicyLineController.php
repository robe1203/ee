<?php

namespace App\Http\Controllers;

use App\Models\Policy;
use App\Models\PolicyLine;
use App\Support\CompanyResolver;
use Illuminate\Http\Request;

class PolicyLineController extends Controller
{
    private function authorizePolicy(Request $request, Policy $policy): void
    {
        $company = CompanyResolver::forUser($request->user());
        abort_unless($policy->company_id === $company->id, 403);
        abort_if($policy->status === 'locked', 403, 'La póliza está bloqueada.');
    }

    private function recalcTotals(Policy $policy): void
    {
        $totals = $policy->lines()
            ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
            ->first();

        $policy->total_debit = $totals->d;
        $policy->total_credit = $totals->c;
        $policy->save();
    }

    public function store(Request $request, Policy $policy)
    {
        $this->authorizePolicy($request, $policy);

        $data = $request->validate([
            'account_id' => ['required','exists:accounts,id'],
            'concept' => ['nullable','string','max:190'],
            'debit' => ['nullable','numeric','min:0'],
            'credit' => ['nullable','numeric','min:0'],
        ]);

        $data['debit'] = $data['debit'] ?? 0;
        $data['credit'] = $data['credit'] ?? 0;

        // Regla simple: no permitir ambos > 0
        if ($data['debit'] > 0 && $data['credit'] > 0) {
            return back()->withErrors(['line' => 'No puedes tener cargo y abono en la misma línea.']);
        }

        $nextNo = (int)($policy->lines()->max('line_no') ?? 0) + 1;

        PolicyLine::create([
            'policy_id' => $policy->id,
            'account_id' => $data['account_id'],
            'concept' => $data['concept'],
            'debit' => $data['debit'],
            'credit' => $data['credit'],
            'line_no' => $nextNo,
        ]);

        $this->recalcTotals($policy);

        return back()->with('success', 'Línea agregada.');
    }

    public function update(Request $request, Policy $policy, PolicyLine $line)
    {
        $this->authorizePolicy($request, $policy);
        abort_unless($line->policy_id === $policy->id, 404);

        $data = $request->validate([
            'account_id' => ['required','exists:accounts,id'],
            'concept' => ['nullable','string','max:190'],
            'debit' => ['nullable','numeric','min:0'],
            'credit' => ['nullable','numeric','min:0'],
        ]);

        $data['debit'] = $data['debit'] ?? 0;
        $data['credit'] = $data['credit'] ?? 0;

        if ($data['debit'] > 0 && $data['credit'] > 0) {
            return back()->withErrors(['line' => 'No puedes tener cargo y abono en la misma línea.']);
        }

        $line->update($data);
        $this->recalcTotals($policy);

        return back()->with('success', 'Línea actualizada.');
    }

    public function destroy(Request $request, Policy $policy, PolicyLine $line)
    {
        $this->authorizePolicy($request, $policy);
        abort_unless($line->policy_id === $policy->id, 404);

        $line->delete();
        $this->recalcTotals($policy);

        return back()->with('success', 'Línea eliminada.');
    }
}