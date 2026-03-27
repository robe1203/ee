<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Company;
use App\Models\Policy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncController extends Controller
{
    public function syncCompanies(Request $request): JsonResponse
    {
        $user = $request->user();

        $companies = Company::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Company $company) => [
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
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'timestamp' => now()->toIso8601String(),
            'companies' => $companies,
        ]);
    }

    public function syncAccounts(Request $request): JsonResponse
    {
        $companyUuid = (string) $request->query('company_uuid', '');
        abort_if($companyUuid === '', 422, 'company_uuid es requerido.');

        $company = Company::query()
            ->where('user_id', $request->user()->id)
            ->where('uuid', $companyUuid)
            ->firstOrFail();

        $accounts = Account::query()
            ->where('company_id', $company->id)
            ->orderBy('code')
            ->get()
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'uuid' => $account->uuid,
                'code' => $account->code,
                'name' => $account->name,
                'nature' => $account->nature,
                'created_at' => $account->created_at?->toIso8601String(),
                'updated_at' => $account->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'timestamp' => now()->toIso8601String(),
            'company_uuid' => $companyUuid,
            'accounts' => $accounts,
        ]);
    }

    public function syncPolicies(Request $request): JsonResponse
    {
        $companyUuid = (string) $request->query('company_uuid', '');
        abort_if($companyUuid === '', 422, 'company_uuid es requerido.');

        $company = Company::query()
            ->where('user_id', $request->user()->id)
            ->where('uuid', $companyUuid)
            ->firstOrFail();

        $policies = Policy::query()
            ->where('company_id', $company->id)
            ->with('lines')
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Policy $policy) => [
                'id' => $policy->id,
                'server_id' => $policy->id,
                'uuid' => $policy->uuid,
                'folio' => $policy->folio,
                'policy_type' => $policy->policy_type,
                'movement_date' => $policy->movement_date?->format('Y-m-d'),
                'status' => $this->normalizeStatusToUi($policy->status),
                'lines' => $policy->lines->map(fn ($line) => [
                    'uuid' => $line->uuid,
                    'account_code' => $line->account_code,
                    'account_name' => $line->account_name,
                    'concept' => $line->concept,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                    'sort' => (int) $line->sort,
                ])->values()->all(),
                'created_at' => $policy->created_at?->toIso8601String(),
                'updated_at' => $policy->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'timestamp' => now()->toIso8601String(),
            'company_uuid' => $companyUuid,
            'policies' => $policies,
        ]);
    }

    public function batchSync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'changes' => 'required|array',
            'changes.*.entity' => 'required|in:company,account,policy',
            'changes.*.action' => 'required|in:create,update,upsert,delete',
            'changes.*.payload' => 'required|array',
        ]);

        $results = [
            'synced' => [],
            'conflicts' => [],
            'errors' => [],
        ];

        foreach ($data['changes'] as $change) {
            try {
                $result = $this->applyChange($request->user(), $change);

                if ($result['type'] === 'synced') {
                    $results['synced'][] = $result;
                } elseif ($result['type'] === 'conflict') {
                    $results['conflicts'][] = $result;
                } else {
                    $results['errors'][] = $result;
                }
            } catch (\Throwable $e) {
                $results['errors'][] = [
                    'entity' => $change['entity'] ?? 'unknown',
                    'action' => $change['action'] ?? 'unknown',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'timestamp' => now()->toIso8601String(),
            'results' => $results,
        ]);
    }

    private function applyChange($user, array $change): array
    {
        $entity = $change['entity'];
        $action = $change['action'] === 'upsert' ? 'update' : $change['action'];
        $payload = $change['payload'];

        return match ($entity) {
            'company' => $this->applyCompanyChange($user->id, $action, $payload),
            'account' => $this->applyAccountChange($user->id, $action, $payload),
            'policy' => $this->applyPolicyChange($user->id, $action, $payload),
            default => ['type' => 'error', 'message' => 'Entidad desconocida'],
        };
    }

    private function applyCompanyChange(int $userId, string $action, array $payload): array
    {
        $uuid = $payload['uuid'] ?? null;
        if (!$uuid) {
            return ['type' => 'error', 'message' => 'UUID requerido para empresa'];
        }

        $company = Company::query()
            ->where('user_id', $userId)
            ->where('uuid', $uuid)
            ->first();

        if ($action === 'delete') {
            if ($company) {
                $company->delete();
            }

            return ['type' => 'synced', 'entity' => 'company', 'uuid' => $uuid, 'action' => 'delete'];
        }

        if (!$company) {
            $company = new Company([
                'uuid' => $uuid,
                'user_id' => $userId,
                'creator_id' => $userId,
            ]);
        }

        $company->fill([
            'name' => $payload['name'] ?? $company->name,
            'rfc' => $payload['rfc'] ?? $company->rfc,
            'regimen_codigo' => $payload['regimen_codigo'] ?? $company->regimen_codigo,
            'regimen_fiscal' => $payload['regimen_fiscal'] ?? $company->regimen_fiscal,
            'address' => $payload['address'] ?? $company->address,
        ]);
        $company->save();

        return [
            'type' => 'synced',
            'entity' => 'company',
            'uuid' => $uuid,
            'action' => $action,
            'version' => $company->version ?? 1,
        ];
    }

    private function applyAccountChange(int $userId, string $action, array $payload): array
    {
        $uuid = $payload['uuid'] ?? null;
        $companyUuid = $payload['company_uuid'] ?? null;

        if (!$uuid || !$companyUuid) {
            return ['type' => 'error', 'message' => 'UUID y company_uuid requeridos'];
        }

        $company = Company::query()
            ->where('user_id', $userId)
            ->where('uuid', $companyUuid)
            ->first();

        if (!$company) {
            return ['type' => 'error', 'message' => 'Empresa no encontrada'];
        }

        $account = Account::query()
            ->where('company_id', $company->id)
            ->where('uuid', $uuid)
            ->first();

        if ($action === 'delete') {
            if ($account) {
                $account->delete();
            }

            return ['type' => 'synced', 'entity' => 'account', 'uuid' => $uuid, 'action' => 'delete'];
        }

        if (!$account) {
            $account = new Account([
                'uuid' => $uuid,
                'company_id' => $company->id,
            ]);
        }

        $account->fill([
            'code' => $payload['code'] ?? $account->code,
            'name' => $payload['name'] ?? $account->name,
            'nature' => ($payload['nature'] ?? $account->nature ?? 'D') === 'A' ? 'A' : 'D',
        ]);
        $account->company_id = $company->id;
        $account->save();

        return [
            'type' => 'synced',
            'entity' => 'account',
            'uuid' => $uuid,
            'action' => $action,
        ];
    }

    private function applyPolicyChange(int $userId, string $action, array $payload): array
    {
        $uuid = $payload['uuid'] ?? $payload['client_uuid'] ?? null;
        $companyUuid = $payload['company_uuid'] ?? null;

        if (!$uuid || !$companyUuid) {
            return ['type' => 'error', 'message' => 'UUID y company_uuid requeridos'];
        }

        $company = Company::query()
            ->where('user_id', $userId)
            ->where('uuid', $companyUuid)
            ->first();

        if (!$company) {
            return ['type' => 'error', 'message' => 'Empresa no encontrada'];
        }

        $policy = Policy::query()
            ->where('company_id', $company->id)
            ->where('uuid', $uuid)
            ->first();

        if ($action === 'delete') {
            if ($policy) {
                $policy->lines()->delete();
                $policy->delete();
            }

            return [
                'type' => 'synced',
                'entity' => 'policy',
                'uuid' => $uuid,
                'company_uuid' => $companyUuid,
                'action' => 'delete',
            ];
        }

        $lines = collect($payload['lines'] ?? [])->map(function ($line, $index) {
            return [
                'uuid' => $line['uuid'] ?? (string) Str::uuid(),
                'account_code' => $line['account_code'] ?? null,
                'account_name' => $line['account_name'] ?? null,
                'concept' => $line['concept'] ?? null,
                'debit' => (float) ($line['debit'] ?? 0),
                'credit' => (float) ($line['credit'] ?? 0),
                'sort' => (int) ($line['sort'] ?? ($index + 1)),
            ];
        })->values();

        $policy = DB::transaction(function () use ($policy, $payload, $company, $uuid, $userId, $lines) {
            if (!$policy) {
                $policy = new Policy([
                    'uuid' => $uuid,
                    'company_id' => $company->id,
                    'user_id' => $userId,
                ]);
            }

            $policy->fill([
                'folio' => (int) ($payload['folio'] ?? $policy->folio ?? 0),
                'policy_type' => $payload['policy_type'] ?? $policy->policy_type ?? 'Diario',
                'movement_date' => $payload['movement_date'] ?? $policy->movement_date ?? now()->toDateString(),
                'status' => $this->normalizeStatusToDb($payload['status'] ?? $policy->status ?? 'Borrador'),
            ]);
            $policy->company_id = $company->id;
            $policy->user_id = $policy->user_id ?: $userId;
            $policy->save();

            $policy->lines()->delete();
            foreach ($lines as $line) {
                $policy->lines()->create($line);
            }

            return $policy->fresh('lines');
        });

        return [
            'type' => 'synced',
            'entity' => 'policy',
            'uuid' => $uuid,
            'company_uuid' => $companyUuid,
            'server_id' => $policy->id,
            'action' => $action,
        ];
    }

    private function normalizeStatusToDb(?string $status): string
    {
        return match (trim((string) $status)) {
            'Registrada', 'locked' => 'locked',
            'Cancelada', 'canceled' => 'canceled',
            default => 'draft',
        };
    }

    private function normalizeStatusToUi(?string $status): string
    {
        return match ((string) $status) {
            'locked' => 'Registrada',
            'canceled' => 'Cancelada',
            default => 'Borrador',
        };
    }
}
