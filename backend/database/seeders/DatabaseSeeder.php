<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('hadirku.admin_initial_password');
        $generated = ! is_string($password) || $password === '';
        $password = $generated ? Str::random(32) : $password;
        if (strlen($password) < 10) {
            throw new \InvalidArgumentException('ADMIN_INITIAL_PASSWORD harus memiliki minimal 10 karakter.');
        }
        $email = strtolower((string) config('hadirku.admin_initial_email'));
        $admin = User::query()->firstOrNew(['email' => $email]);

        $admin->name = (string) config('hadirku.admin_initial_name');
        $admin->username = strtoupper((string) config('hadirku.admin_initial_username'));
        $admin->role = 'admin';
        if (! $generated || ! $admin->exists) {
            $admin->password = $password;
        }
        $admin->save();

        if ($generated) {
            fwrite(STDOUT, "Kata sandi awal admin (tampil sekali): {$password}".PHP_EOL);
        }
    }
}
