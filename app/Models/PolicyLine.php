<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PolicyLine extends Model
{
    protected $fillable = [
        'uuid',
        'policy_id',
        'account_id',
        'account_code',
        'account_name',
        'concept',
        'debit',
        'credit',
        'sort',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function policy()
    {
        return $this->belongsTo(Policy::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}