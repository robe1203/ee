<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Companies
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            }
        });

        // Accounts
        Schema::table('accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('accounts', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            }
        });

        // Policies
        Schema::table('policies', function (Blueprint $table) {
            if (!Schema::hasColumn('policies', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            }
        });

        // Policy lines
        Schema::table('policy_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('policy_lines', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            }
        });

        // Backfill UUIDs for existing rows (safe to re-run)
        DB::table('companies')->whereNull('uuid')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $r) {
                DB::table('companies')->where('id', $r->id)->update(['uuid' => (string) Str::uuid()]);
            }
        });

        DB::table('accounts')->whereNull('uuid')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $r) {
                DB::table('accounts')->where('id', $r->id)->update(['uuid' => (string) Str::uuid()]);
            }
        });

        DB::table('policies')->whereNull('uuid')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $r) {
                DB::table('policies')->where('id', $r->id)->update(['uuid' => (string) Str::uuid()]);
            }
        });

        DB::table('policy_lines')->whereNull('uuid')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $r) {
                DB::table('policy_lines')->where('id', $r->id)->update(['uuid' => (string) Str::uuid()]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('policy_lines', function (Blueprint $table) {
            if (Schema::hasColumn('policy_lines', 'uuid')) {
                $table->dropColumn('uuid');
            }
        });

        Schema::table('policies', function (Blueprint $table) {
            if (Schema::hasColumn('policies', 'uuid')) {
                $table->dropColumn('uuid');
            }
        });

        Schema::table('accounts', function (Blueprint $table) {
            if (Schema::hasColumn('accounts', 'uuid')) {
                $table->dropColumn('uuid');
            }
        });

        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'uuid')) {
                $table->dropColumn('uuid');
            }
        });
    }
};