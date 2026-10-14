<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('face_distance', 8, 6)->nullable();
            $table->decimal('match_score', 5, 2)->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('liveness_verified')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropColumn([
                'latitude',
                'longitude',
                'face_distance',
                'match_score',
                'photo_path',
                'liveness_verified',
            ]);
        });
    }
};
