<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;

class CompanyResolver
{
    public static function forUser(User $user): ?Company
    {
        $companyId = session('company_id');

        if ($companyId) {
            $company = Company::where('id', $companyId)
                ->where('user_id', $user->id)
                ->first();

            if ($company) {
                return $company;
            }
        }

        $first = Company::where('user_id', $user->id)
            ->orderBy('id')
            ->first();

        if ($first) {
            session(['company_id' => $first->id]);
            return $first;
        }

        return null;
    }
}
