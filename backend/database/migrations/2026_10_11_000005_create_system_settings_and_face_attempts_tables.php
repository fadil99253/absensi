<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->string('key', 64)->primary();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('face_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_id', 24)->nullable()->index();
            $table->boolean('passed');
            $table->string('reason', 120)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->foreign('employee_id')->references('employee_id')->on('employees')->nullOnDelete();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_attempts');
        Schema::dropIfExists('system_settings');
    }
};
