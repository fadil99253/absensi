<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table): void {
            $table->string('employee_id', 24)->primary();
            $table->string('name', 80);
            $table->string('role', 80)->default('Karyawan');
            $table->string('department', 60);
            $table->string('email', 120)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
