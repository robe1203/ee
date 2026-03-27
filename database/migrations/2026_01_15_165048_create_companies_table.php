<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void
{
    Schema::create('companies', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // dueño
        $table->string('name');
        $table->string('rfc')->nullable();
        $table->string('regimen_codigo')->nullable();
        $table->string('regimen_fiscal')->nullable();
        $table->string('address')->nullable();
        $table->timestamps();
    });
}


  public function down(): void
  {
    Schema::dropIfExists('companies');
  }
};

