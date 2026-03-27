<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Company;
use App\Support\CompanyResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CatalogController extends Controller
{
    private function companyFor(Request $request): Company
    {
        $company = CompanyResolver::forUser($request->user());

        if (!$company) {
            abort(403, 'No hay empresa seleccionada.');
        }

        return $company;
    }

    public function index(Request $request)
    {
        $company = $this->companyFor($request);

        return Inertia::render('App/Catalog', [
            'accounts' => Account::query()
                ->where('company_id', $company->id)
                ->orderBy('code')
                ->get(['id', 'uuid', 'company_id', 'code', 'name', 'nature']),
            'currentCompanyUuid' => $company->uuid,
            'currentCompanyId' => $company->id,
        ]);
    }

    public function storeCustom(Request $request)
    {
        $user = $request->user();
        $activeCompany = CompanyResolver::forUser($user);

        $data = $request->validate([
            '_action' => ['nullable', 'in:create,upsert,delete'],
            'uuid' => ['nullable', 'string', 'max:100'],
            'company_uuid' => ['nullable', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:255'],
            'nature' => ['nullable', 'in:D,A'],
        ]);

        $action = $data['_action'] ?? 'upsert';
        $company = null;

        if (!empty($data['company_uuid'])) {
            $company = Company::where('user_id', $user->id)
                ->where('uuid', $data['company_uuid'])
                ->first();
        }

        $company ??= $activeCompany;

        if (!$company) {
            $message = 'No hay empresa seleccionada.';
            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->withErrors(['company' => $message]);
        }

        if ($action === 'delete') {
            $account = Account::where('company_id', $company->id)
                ->where('uuid', $data['uuid'] ?? '')
                ->first();

            if ($account) {
                $account->delete();
            }

            return $request->expectsJson()
                ? response()->json(['message' => 'Cuenta eliminada.'])
                : back()->with('success', 'Cuenta eliminada.');
        }

        $payload = [
            'code' => trim((string) ($data['code'] ?? '')),
            'name' => trim((string) ($data['name'] ?? '')),
            'nature' => $data['nature'] ?? 'D',
        ];

        if ($payload['code'] === '' || $payload['name'] === '') {
            $message = 'Debes indicar código y nombre.';
            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->withErrors(['code' => $message]);
        }

        $duplicateByCode = Account::where('company_id', $company->id)
            ->where('code', $payload['code'])
            ->when(!empty($data['uuid']), fn ($q) => $q->where('uuid', '!=', $data['uuid']))
            ->exists();

        if ($duplicateByCode) {
            $message = 'Ese código ya existe en el catálogo.';
            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->withErrors(['code' => $message]);
        }

        $account = DB::transaction(function () use ($company, $data, $payload) {
            $uuid = $data['uuid'] ?? (string) Str::uuid();

            $account = Account::firstOrNew([
                'company_id' => $company->id,
                'uuid' => $uuid,
            ]);

            $account->fill($payload);
            $account->save();

            return $account;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Cuenta agregada.',
                'account' => [
                    'id' => $account->id,
                    'uuid' => $account->uuid,
                    'company_id' => $account->company_id,
                    'company_uuid' => $company->uuid,
                    'code' => $account->code,
                    'name' => $account->name,
                    'nature' => $account->nature,
                ],
            ]);
        }

        return back()->with('success', 'Cuenta agregada.');
    }

    public function destroy(Request $request, Account $account)
    {
        $company = $this->companyFor($request);

        if ((int) $account->company_id !== (int) $company->id) {
            abort(403);
        }

        $account->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Cuenta eliminada.']);
        }

        return back()->with('success', 'Cuenta eliminada.');
    }
}
