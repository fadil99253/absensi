<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_face_profiles', function (Blueprint $table): void {
            $table->string('employee_id', 24)->primary();
            $table->longText('descriptor');
            $table->timestamp('enrolled_at');
            $table->timestamps();
            $table->foreign('employee_id')->references('employee_id')->on('employees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_face_profiles');
    }
};
