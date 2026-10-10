<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@hadirku.id'],
            [
                'name' => 'Admin HadirKu',
                'password' => Hash::make('hadirku123'),
            ],
        );

        $employees = [
            ['EMP-001', 'Rizki Pratama', 'Product Designer', 'Produk', 'rizki@hadirku.id'],
            ['EMP-002', 'Nadia Putri', 'Frontend Developer', 'Engineering', 'nadia@hadirku.id'],
            ['EMP-003', 'Bima Saputra', 'Marketing Specialist', 'Marketing', 'bima@hadirku.id'],
            ['EMP-004', 'Salsa Maharani', 'People Operations', 'People', 'salsa@hadirku.id'],
            ['EMP-005', 'Dimas Kurniawan', 'Backend Developer', 'Engineering', 'dimas@hadirku.id'],
            ['EMP-006', 'Alya Rahma', 'Finance Associate', 'Finance', 'alya@hadirku.id'],
        ];

        foreach ($employees as [$id, $name, $role, $department, $email]) {
            Employee::query()->updateOrCreate(
                ['employee_id' => $id],
                [
                    'name' => $name,
                    'role' => $role,
                    'department' => $department,
                    'email' => $email,
                    'active' => true,
                ],
            );
        }
    }
}
