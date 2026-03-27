<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\CompanyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CompanyManageController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $company = CompanyResolver::forUser($user);

        $companies = Company::where('user_id', $user->id)
            ->orderByDesc('id')
            ->get(['id', 'uuid', 'name', 'rfc', 'regimen_codigo', 'regimen_fiscal', 'address']);

        return Inertia::render('App/Company', [
            'company' => $company ? [
                'id' => $company->id,
                'uuid' => $company->uuid,
                'name' => $company->name,
                'rfc' => $company->rfc,
                'regimen_codigo' => $company->regimen_codigo,
                'regimen_fiscal' => $company->regimen_fiscal,
                'address' => $company->address,
            ] : null,
            'companies' => $companies,
            'currentCompanyId' => session('company_id'),
            'currentCompanyUuid' => $company?->uuid,
            'regimens' => $this->regimens(),
        ]);
    }

    public function saveActive(Request $request)
    {
        $company = CompanyResolver::forUser($request->user());

        if (!$company) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Primero crea una empresa.'], 422);
            }

            return redirect()->route('app.company.index')->with('error', 'Primero crea una empresa.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rfc' => ['nullable', 'string', 'max:13'],
            'regimen_codigo' => ['nullable', 'string', 'max:10'],
            'regimen_fiscal' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $company->update($data);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Empresa actualizada correctamente.',
                'company' => $this->companyPayload($company->fresh()),
            ]);
        }

        return back()->with('success', 'Empresa actualizada correctamente.');
    }

    public function store(Request $request): JsonResponse|
        \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            '_action' => ['nullable', 'in:create,upsert,delete'],
            'uuid' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:255'],
            'rfc' => ['nullable', 'string', 'max:13'],
            'regimen_codigo' => ['nullable', 'string', 'max:10'],
            'regimen_fiscal' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'select_after' => ['nullable', 'boolean'],
        ]);

        $action = $data['_action'] ?? 'upsert';

        if ($action === 'delete') {
            $company = Company::where('user_id', $user->id)
                ->where('uuid', $data['uuid'] ?? '')
                ->first();

            if ($company) {
                $wasActive = (int) session('company_id') === (int) $company->id;
                $company->delete();
                if ($wasActive) {
                    session()->forget('company_id');
                }
            }

            return response()->json(['message' => 'Empresa eliminada.']);
        }

        $uuid = $data['uuid'] ?? (string) Str::uuid();
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            $count = Company::where('user_id', $user->id)->count();
            $name = 'Empresa ' . ($count + 1);
        }

        $company = DB::transaction(function () use ($user, $uuid, $data, $name) {
            $company = Company::firstOrNew([
                'user_id' => $user->id,
                'uuid' => $uuid,
            ]);

            $company->fill([
                'name' => $name,
                'rfc' => $data['rfc'] ?? $company->rfc,
                'regimen_codigo' => $data['regimen_codigo'] ?? $company->regimen_codigo,
                'regimen_fiscal' => $data['regimen_fiscal'] ?? $company->regimen_fiscal,
                'address' => $data['address'] ?? $company->address,
            ]);

            $company->save();

            return $company;
        });

        if (($data['select_after'] ?? true) === true) {
            session(['company_id' => $company->id]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Empresa guardada correctamente.',
                'company' => $this->companyPayload($company->fresh()),
                'selected_company_id' => session('company_id'),
            ]);
        }

        return back()->with('success', 'Empresa guardada correctamente.');
    }

    public function quickCreate(Request $request)
    {
        $user = $request->user();
        $count = Company::where('user_id', $user->id)->count();

        $company = Company::create([
            'user_id' => $user->id,
            'name' => 'Empresa ' . ($count + 1),
            'rfc' => null,
            'regimen_codigo' => null,
            'regimen_fiscal' => null,
            'address' => null,
        ]);

        session(['company_id' => $company->id]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Empresa creada correctamente.',
                'company' => $this->companyPayload($company),
            ]);
        }

        return back()->with('success', 'Empresa creada correctamente.');
    }

    public function select(Request $request, Company $company)
    {
        if ((int) $company->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        session(['company_id' => $company->id]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Empresa seleccionada.',
                'company' => $this->companyPayload($company),
            ]);
        }

        return back()->with('success', 'Empresa seleccionada.');
    }

    public function update(Request $request, Company $company)
    {
        if ((int) $company->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rfc' => ['nullable', 'string', 'max:13'],
            'regimen_codigo' => ['nullable', 'string', 'max:10'],
            'regimen_fiscal' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $company->update($data);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Empresa actualizada.',
                'company' => $this->companyPayload($company->fresh()),
            ]);
        }

        return back()->with('success', 'Empresa actualizada.');
    }

    public function destroy(Request $request, Company $company)
    {
        if ((int) $company->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $companyId = session('company_id');
        $company->delete();

        if ((int) $companyId === (int) $company->id) {
            session()->forget('company_id');
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Empresa eliminada.']);
        }

        return back()->with('success', 'Empresa eliminada.');
    }

    private function companyPayload(Company $company): array
    {
        return [
            'id' => $company->id,
            'uuid' => $company->uuid,
            'name' => $company->name,
            'rfc' => $company->rfc,
            'regimen_codigo' => $company->regimen_codigo,
            'regimen_fiscal' => $company->regimen_fiscal,
            'address' => $company->address,
            'version' => $company->version ?? 1,
            'creator_id' => $company->creator_id,
            'created_at' => $company->created_at?->toIso8601String(),
            'updated_at' => $company->updated_at?->toIso8601String(),
        ];
    }

    private function regimens(): array
    {
        return [
            ['code' => '601', 'label' => 'General de Ley Personas Morales'],
            ['code' => '603', 'label' => 'Personas Morales con Fines no Lucrativos'],
            ['code' => '605', 'label' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios'],
            ['code' => '606', 'label' => 'Arrendamiento'],
            ['code' => '607', 'label' => 'Régimen de Enajenación o Adquisición de Bienes'],
            ['code' => '608', 'label' => 'Demás ingresos'],
            ['code' => '610', 'label' => 'Residentes en el Extranjero sin Establecimiento Permanente en México'],
            ['code' => '611', 'label' => 'Ingresos por Dividendos (socios y accionistas)'],
            ['code' => '612', 'label' => 'Personas Físicas con Actividades Empresariales y Profesionales'],
            ['code' => '614', 'label' => 'Ingresos por intereses'],
            ['code' => '615', 'label' => 'Régimen de los ingresos por obtención de premios'],
            ['code' => '616', 'label' => 'Sin obligaciones fiscales'],
            ['code' => '620', 'label' => 'Sociedades Cooperativas de Producción que optan por diferir sus ingresos'],
            ['code' => '621', 'label' => 'Incorporación Fiscal'],
            ['code' => '622', 'label' => 'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras'],
            ['code' => '623', 'label' => 'Opcional para Grupos de Sociedades'],
            ['code' => '624', 'label' => 'Coordinados'],
            ['code' => '628', 'label' => 'Hidrocarburos'],
            ['code' => '629', 'label' => 'De los Regímenes Fiscales Preferentes y de las Empresas Multinacionales'],
            ['code' => '630', 'label' => 'Enajenación de acciones en bolsa de valores'],
        ];
    }
}
