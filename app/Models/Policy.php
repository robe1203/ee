<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Policy extends Model
{
    protected $fillable = [
        'uuid',
        'company_id','user_id','folio','policy_type','movement_date','status'
    ];

    protected $casts = [
        'movement_date' => 'date:Y-m-d',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function lines()
    {
        return $this->hasMany(PolicyLine::class)->orderBy('sort');
    }
}