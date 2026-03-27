<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('folio')->default(1000);
            $table->enum('policy_type', ['Ingreso','Egreso','Diario','Compras','Nóminas']);
            $table->date('movement_date');

            $table->enum('status', ['draft','locked'])->default('draft');

            $table->timestamps();

            $table->index(['company_id','movement_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policies');
    }
};