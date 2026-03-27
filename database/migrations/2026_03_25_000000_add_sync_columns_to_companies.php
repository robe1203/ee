<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // creator_id: quién creó la empresa (para seguridad en import/export)
            if (!Schema::hasColumn('companies', 'creator_id')) {
                $table->foreignId('creator_id')->nullable()->after('user_id')->constrained('users')->nullifyOnDelete();
            }

            // version: para detectar cambios y evitar conflicts
            if (!Schema::hasColumn('companies', 'version')) {
                $table->integer('version')->default(1)->after('address');
            }

            // data_hash: hash de los datos principales para detectar cambios
            if (!Schema::hasColumn('companies', 'data_hash')) {
                $table->string('data_hash')->nullable()->after('version');
            }

            // last_synced_at: cuándo se sincronizó por última vez
            if (!Schema::hasColumn('companies', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('data_hash');
            }
        });

        // Backfill: creator_id = user_id (datos existentes)
        \DB::table('companies')
            ->whereNull('creator_id')
            ->update(['creator_id' => \DB::raw('user_id')]);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'creator_id')) {
                $table->dropConstrainedForeignId('creator_id');
            }
            if (Schema::hasColumn('companies', 'version')) {
                $table->dropColumn('version');
            }
            if (Schema::hasColumn('companies', 'data_hash')) {
                $table->dropColumn('data_hash');
            }
            if (Schema::hasColumn('companies', 'last_synced_at')) {
                $table->dropColumn('last_synced_at');
            }
        });
    }
};
