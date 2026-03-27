<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE policies MODIFY status ENUM('draft','locked','canceled') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("UPDATE policies SET status='draft' WHERE status NOT IN ('draft','locked')");

        DB::statement("ALTER TABLE policies MODIFY status ENUM('draft','locked') NOT NULL DEFAULT 'draft'");
    }
};