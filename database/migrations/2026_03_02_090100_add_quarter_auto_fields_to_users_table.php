<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // Fecha "ancla" del cuatrimestre (cuando inició el cuatrimestre actual)
            if (!Schema::hasColumn('users', 'quarter_started_at')) {
                $table->timestamp('quarter_started_at')->nullable()->after('quarter');
            }

            // Próxima fecha en la que debe subir 1 cuatrimestre (cada 4 meses)
            if (!Schema::hasColumn('users', 'quarter_next_at')) {
                $table->timestamp('quarter_next_at')->nullable()->after('quarter_started_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'quarter_next_at')) {
                $table->dropColumn('quarter_next_at');
            }
            if (Schema::hasColumn('users', 'quarter_started_at')) {
                $table->dropColumn('quarter_started_at');
            }
        });
    }
};