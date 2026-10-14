<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 32)->nullable()->unique()->after('name');
            $table->string('role', 20)->default('karyawan')->after('password');
        });

        DB::table('users')->orderBy('id')->get(['id', 'email'])->each(function (object $user): void {
            $candidate = preg_replace('/[^a-zA-Z0-9._-]/', '', explode('@', $user->email)[0]) ?: 'user';
            $suffix = '-'.$user->id;
            $username = substr($candidate, 0, 32 - strlen($suffix)).$suffix;
            $role = strtolower($user->email) === 'admin@hadirku.id' ? 'admin' : 'karyawan';

            DB::table('users')->where('id', $user->id)->update([
                'username' => $username,
                'role' => $role,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'role']);
        });
    }
};
