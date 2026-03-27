<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Company extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'creator_id',
        'name',
        'rfc',
        'regimen_codigo',
        'regimen_fiscal',
        'address',
        'version',
        'data_hash',
        'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            // creator_id por defecto es el user_id (quien la crea)
            if (empty($model->creator_id)) {
                $model->creator_id = $model->user_id;
            }
            // version inicial
            if (empty($model->version)) {
                $model->version = 1;
            }
        });

        static::updating(function ($model) {
            // Incrementar version en cada actualización
            $model->version = ($model->version ?? 0) + 1;
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}