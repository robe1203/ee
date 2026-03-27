<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();

            // Relación con empresa
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // Campos del catálogo
            $table->string('code', 50);      // ej: 1000-01
            $table->string('name', 255);     // ej: Caja
            $table->enum('nature', ['D', 'A'])->default('D'); // D=Deudora, A=Acreedora

            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
