<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Company;
use App\Models\Policy;
use App\Models\PolicyLine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyTransferController extends Controller
{
    public function export(Request $request, Company $company): StreamedResponse
    {
        $user = $request->user();

        abort_unless((int) $company->user_id === (int) $user->id, 403);

        $accounts = Account::query()
            ->where('company_id', $company->id)
            ->orderBy('code')
            ->get();

        $policies = Policy::query()
            ->where('company_id', $company->id)
            ->orderBy('movement_date')
            ->orderBy('folio')
            ->get();

        $policyIds = $policies->pluck('id')->all();
        $lines = empty($policyIds)
            ? collect()
            : PolicyLine::query()
                ->whereIn('policy_id', $policyIds)
                ->orderBy('policy_id')
                ->orderBy('sort')
                ->get();

        $policyUuidById = $policies->pluck('uuid', 'id');
        $accountUuidById = $accounts->pluck('uuid', 'id');
        $exportedAt = now()->toIso8601String();
        $deviceId = (string) Str::uuid();

        $payload = [
            'schema_version' => 3,
            'exported_at' => $exportedAt,
            'device_id' => $deviceId,
            'security' => [
                'creator_id' => $company->creator_id ?: $company->user_id,
                'owner_id' => $company->user_id,
            ],
            'company' => [
                'uuid' => $company->uuid,
                'name' => $company->name,
                'rfc' => $company->rfc,
                'regimen_codigo' => $company->regimen_codigo,
                'regimen_fiscal' => $company->regimen_fiscal,
                'address' => $company->address,
                'image_membrete' => $company->image_membrete,
                'created_at' => optional($company->created_at)->toIso8601String(),
                'updated_at' => optional($company->updated_at)->toIso8601String(),
            ],
            'accounts' => $accounts->map(fn (Account $account) => [
                'uuid' => $account->uuid,
                'code' => $account->code,
                'name' => $account->name,
                'nature' => $account->nature,
                'created_at' => optional($account->created_at)->toIso8601String(),
                'updated_at' => optional($account->updated_at)->toIso8601String(),
            ])->values()->all(),
            'policies' => $policies->map(fn (Policy $policy) => [
                'uuid' => $policy->uuid,
                'folio' => $policy->folio,
                'policy_type' => $policy->policy_type,
                'movement_date' => optional($policy->movement_date)->format('Y-m-d'),
                'status' => $policy->status,
                'created_at' => optional($policy->created_at)->toIso8601String(),
                'updated_at' => optional($policy->updated_at)->toIso8601String(),
            ])->values()->all(),
            'policy_lines' => $lines->map(function (PolicyLine $line) use ($policyUuidById, $accountUuidById) {
                return [
                    'uuid' => $line->uuid,
                    'policy_uuid' => $policyUuidById[$line->policy_id] ?? null,
                    'account_uuid' => $accountUuidById[$line->account_id] ?? null,
                    'account_code' => $line->account_code,
                    'account_name' => $line->account_name,
                    'concept' => $line->concept,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                    'sort' => (int) $line->sort,
                    'created_at' => optional($line->created_at)->toIso8601String(),
                    'updated_at' => optional($line->updated_at)->toIso8601String(),
                ];
            })->values()->all(),
        ];

        $payload['signature'] = $this->signPayload($payload);

        $filename = 'empresa_' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', $company->name) . '_' . now()->format('Ymd_His') . '.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }, $filename, [
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
    }

    public function import(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'file' => ['required', 'file', 'mimes:json,txt'],
            'force' => ['nullable', 'boolean'],
        ]);

        $raw = file_get_contents($request->file('file')->getRealPath());
        $json = json_decode($raw, true);

        if (!is_array($json) || !in_array((int) ($json['schema_version'] ?? 0), [1, 2, 3], true)) {
            return back()->with('error', 'Archivo inválido o versión no compatible.');
        }

        $companyPayload = $json['company'] ?? null;
        if (!is_array($companyPayload) || empty($companyPayload['uuid'])) {
            return back()->with('error', 'El archivo no contiene una empresa válida.');
        }

        if ((int) ($json['schema_version'] ?? 0) >= 3) {
            if (!$this->isValidSignature($json)) {
                return redirect()->route('app.import.blocked')->with([
                    'message' => 'El archivo fue alterado o su firma no es válida. No se puede importar.',
                ]);
            }

            $creatorId = (int) ($json['security']['creator_id'] ?? 0);
            if ($creatorId > 0 && $creatorId !== (int) $user->id) {
                return redirect()->route('app.import.blocked')->with([
                    'message' => 'Esta empresa fue creada por otro usuario. No puedes importar empresas que no creaste.',
                ]);
            }
        }

        $duplicateCompany = Company::query()->where('uuid', $companyPayload['uuid'])->first();

        if ($duplicateCompany && (int) $duplicateCompany->user_id !== (int) $user->id) {
            return redirect()->route('app.import.blocked')->with([
                'message' => 'Intentaste importar una empresa que ya existe en el sistema. Copiar el trabajo de otro no te ayudará a aprender.',
            ]);
        }

        if ($duplicateCompany && $duplicateCompany->creator_id && (int) $duplicateCompany->creator_id !== (int) $user->id) {
            return redirect()->route('app.import.blocked')->with([
                'message' => 'Esta empresa fue creada por otro usuario. No puedes importar empresas que no creaste.',
            ]);
        }

        $result = DB::transaction(function () use ($json, $companyPayload, $user, $duplicateCompany) {
            $isMerge = (bool) $duplicateCompany;
            $company = $duplicateCompany ?: new Company();

            if (!$duplicateCompany) {
                $company->uuid = $companyPayload['uuid'];
                $company->user_id = $user->id;
                $company->creator_id = $user->id;
                $company->name = $companyPayload['name'] ?? 'Empresa importada';
                $company->rfc = $companyPayload['rfc'] ?? null;
                $company->regimen_codigo = $companyPayload['regimen_codigo'] ?? null;
                $company->regimen_fiscal = $companyPayload['regimen_fiscal'] ?? null;
                $company->address = $companyPayload['address'] ?? null;
                if (array_key_exists('image_membrete', $companyPayload)) {
                    $company->image_membrete = $companyPayload['image_membrete'];
                }
                $company->save();
            } else {
                if ($this->incomingIsNewer($company->updated_at, $companyPayload['updated_at'] ?? null)) {
                    $company->fill([
                        'name' => $companyPayload['name'] ?? $company->name,
                        'rfc' => $companyPayload['rfc'] ?? $company->rfc,
                        'regimen_codigo' => $companyPayload['regimen_codigo'] ?? $company->regimen_codigo,
                        'regimen_fiscal' => $companyPayload['regimen_fiscal'] ?? $company->regimen_fiscal,
                        'address' => $companyPayload['address'] ?? $company->address,
                    ]);
                    if (array_key_exists('image_membrete', $companyPayload)) {
                        $company->image_membrete = $companyPayload['image_membrete'];
                    }
                    $company->save();
                }
            }

            $companyId = $company->id;
            $accountIdByUuid = [];
            $lineGroups = collect($json['policy_lines'] ?? [])->groupBy('policy_uuid');
            $stats = [
                'accounts_created' => 0,
                'accounts_updated' => 0,
                'policies_created' => 0,
                'policies_updated' => 0,
            ];

            foreach (($json['accounts'] ?? []) as $accountPayload) {
                if (empty($accountPayload['uuid'])) {
                    continue;
                }

                $account = Account::query()
                    ->where('company_id', $companyId)
                    ->where(function ($query) use ($accountPayload) {
                        $query->where('uuid', $accountPayload['uuid']);
                        if (!empty($accountPayload['code'])) {
                            $query->orWhere('code', $accountPayload['code']);
                        }
                    })
                    ->first();

                if (!$account) {
                    $account = Account::create([
                        'uuid' => $accountPayload['uuid'],
                        'company_id' => $companyId,
                        'code' => (string) ($accountPayload['code'] ?? ''),
                        'name' => (string) ($accountPayload['name'] ?? ''),
                        'nature' => ($accountPayload['nature'] ?? 'D') === 'A' ? 'A' : 'D',
                    ]);
                    $stats['accounts_created']++;
                } else {
                    if (empty($account->uuid)) {
                        $account->uuid = $accountPayload['uuid'];
                    }

                    if ($this->incomingIsNewer($account->updated_at, $accountPayload['updated_at'] ?? null)) {
                        $account->fill([
                            'code' => (string) ($accountPayload['code'] ?? $account->code),
                            'name' => (string) ($accountPayload['name'] ?? $account->name),
                            'nature' => ($accountPayload['nature'] ?? $account->nature) === 'A' ? 'A' : 'D',
                        ]);
                        $account->save();
                        $stats['accounts_updated']++;
                    }
                }

                $accountIdByUuid[$accountPayload['uuid']] = $account->id;
            }

            foreach (($json['policies'] ?? []) as $policyPayload) {
                if (empty($policyPayload['uuid'])) {
                    continue;
                }

                $policy = Policy::query()
                    ->where('company_id', $companyId)
                    ->where('uuid', $policyPayload['uuid'])
                    ->first();

                if (!$policy) {
                    $policy = Policy::create([
                        'uuid' => $policyPayload['uuid'],
                        'company_id' => $companyId,
                        'user_id' => $user->id,
                        'folio' => (int) ($policyPayload['folio'] ?? 0),
                        'policy_type' => $this->normalizePolicyType($policyPayload['policy_type'] ?? 'Diario'),
                        'movement_date' => $policyPayload['movement_date'] ?? now()->toDateString(),
                        'status' => $this->normalizePolicyStatus($policyPayload['status'] ?? 'draft'),
                    ]);
                    $stats['policies_created']++;
                    $shouldReplaceLines = true;
                } else {
                    $shouldReplaceLines = $this->incomingIsNewer($policy->updated_at, $policyPayload['updated_at'] ?? null);

                    if ($shouldReplaceLines) {
                        $policy->update([
                            'folio' => (int) ($policyPayload['folio'] ?? $policy->folio),
                            'policy_type' => $this->normalizePolicyType($policyPayload['policy_type'] ?? $policy->policy_type),
                            'movement_date' => $policyPayload['movement_date'] ?? $policy->movement_date,
                            'status' => $this->normalizePolicyStatus($policyPayload['status'] ?? $policy->status),
                        ]);
                        $stats['policies_updated']++;
                    }
                }

                if (!$shouldReplaceLines) {
                    continue;
                }

                $incomingLines = $lineGroups->get($policyPayload['uuid'], collect());
                $policy->lines()->delete();

                foreach ($incomingLines as $index => $linePayload) {
                    $accountUuid = $linePayload['account_uuid'] ?? null;

                    $policy->lines()->create([
                        'uuid' => $linePayload['uuid'] ?? (string) Str::uuid(),
                        'account_id' => $accountUuid && isset($accountIdByUuid[$accountUuid]) ? $accountIdByUuid[$accountUuid] : null,
                        'account_code' => $linePayload['account_code'] ?? null,
                        'account_name' => $linePayload['account_name'] ?? null,
                        'concept' => $linePayload['concept'] ?? null,
                        'debit' => (float) ($linePayload['debit'] ?? 0),
                        'credit' => (float) ($linePayload['credit'] ?? 0),
                        'sort' => (int) ($linePayload['sort'] ?? ($index + 1)),
                    ]);
                }
            }

            return [
                'company' => $company,
                'is_merge' => $isMerge,
                'stats' => $stats,
            ];
        });

        session(['company_id' => $result['company']->id]);

        $message = $result['is_merge']
            ? 'Empresa fusionada correctamente. Cuentas nuevas: ' . $result['stats']['accounts_created'] . ', cuentas actualizadas: ' . $result['stats']['accounts_updated'] . ', pólizas nuevas: ' . $result['stats']['policies_created'] . ', pólizas actualizadas: ' . $result['stats']['policies_updated'] . '.'
            : 'Empresa importada correctamente.';

        return back()->with('success', $message);
    }

    private function normalizePolicyType(?string $value): string
    {
        $value = trim((string) $value);

        return match (mb_strtolower($value)) {
            'ingreso' => 'Ingreso',
            'egreso' => 'Egreso',
            'compras' => 'Compras',
            'nóminas', 'nominas' => 'Nóminas',
            default => 'Diario',
        };
    }

    private function normalizePolicyStatus(?string $value): string
    {
        $value = trim((string) $value);

        return match (mb_strtolower($value)) {
            'registrada', 'locked' => 'locked',
            'cancelada', 'canceled' => 'canceled',
            default => 'draft',
        };
    }

    private function incomingIsNewer($currentTimestamp, ?string $incomingTimestamp): bool
    {
        if (!$incomingTimestamp) {
            return true;
        }

        try {
            $incoming = Carbon::parse($incomingTimestamp);
        } catch (\Throwable $e) {
            return true;
        }

        if (!$currentTimestamp) {
            return true;
        }

        try {
            $current = $currentTimestamp instanceof Carbon ? $currentTimestamp : Carbon::parse($currentTimestamp);
        } catch (\Throwable $e) {
            return true;
        }

        return $incoming->greaterThanOrEqualTo($current);
    }

    private function signPayload(array $payload): string
    {
        $copy = $payload;
        unset($copy['signature']);

        return hash_hmac('sha256', json_encode($copy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), config('app.key'));
    }

    private function isValidSignature(array $payload): bool
    {
        $incoming = (string) ($payload['signature'] ?? '');
        if ($incoming === '') {
            return false;
        }

        return hash_equals($this->signPayload($payload), $incoming);
    }
}
