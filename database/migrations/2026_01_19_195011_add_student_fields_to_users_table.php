<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // Agregar quarter si NO existe
            if (!Schema::hasColumn('users', 'quarter')) {
                $table->unsignedTinyInteger('quarter')->nullable()->after('email'); // 1-11
            }

            // Agregar is_active si NO existe
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('quarter');
            }

        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            if (Schema::hasColumn('users', 'quarter')) {
                $table->dropColumn('quarter');
            }

            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropColumn('is_active');
            }

        });
    }
};
