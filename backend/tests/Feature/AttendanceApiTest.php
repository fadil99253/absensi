<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_user_can_log_in(): void
    {
        User::query()->create([
            'name' => 'Admin HadirKu',
            'email' => 'admin@hadirku.id',
            'password' => Hash::make('hadirku123'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'admin@hadirku.id',
            'password' => 'hadirku123',
        ])->assertOk()
            ->assertJsonPath('user.email', 'admin@hadirku.id')
            ->assertJsonStructure(['token']);
    }

    public function test_api_data_requires_a_valid_token(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
        $this->getJson('/api/employees')->assertUnauthorized();
        $this->postJson('/api/employees', [])->assertUnauthorized();
        $this->getJson('/api/attendance')->assertUnauthorized();
        $this->postJson('/api/attendance', [])->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
    }

    public function test_unauthenticated_api_requests_without_json_accept_header_return_json(): void
    {
        $this->get('/api/dashboard')
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/json');

        $this->get('/api/employees')
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/json');

        $this->get('/api/attendance')
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/json');

        $this->post('/api/logout')
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_employee_can_check_in_and_check_out_once_per_day(): void
    {
        Employee::query()->create([
            'employee_id' => 'EMP-TEST',
            'name' => 'Karyawan Tes',
            'department' => 'Produk',
            'role' => 'Tester',
            'active' => true,
        ]);

        $payload = ['employeeId' => 'EMP-TEST', 'type' => 'Masuk'];

        $user = User::query()->create([
            'name' => 'Admin HadirKu',
            'email' => 'admin@hadirku.id',
            'password' => Hash::make('hadirku123'),
        ]);
        $this->withToken($user->createToken('test')->plainTextToken);

        $this->postJson('/api/attendance', $payload)
            ->assertCreated()
            ->assertJsonPath('employeeId', 'EMP-TEST')
            ->assertJsonPath('type', 'Masuk');

        $this->postJson('/api/attendance', $payload)->assertUnprocessable();

        $this->postJson('/api/attendance', [
            'employeeId' => 'EMP-TEST',
            'type' => 'Pulang',
        ])->assertCreated()->assertJsonPath('type', 'Pulang');
    }

    public function test_employee_must_check_in_before_checking_out_and_cannot_check_out_twice(): void
    {
        Employee::query()->create([
            'employee_id' => 'EMP-TEST',
            'name' => 'Karyawan Tes',
            'department' => 'Produk',
            'active' => true,
        ]);

        $user = User::query()->create([
            'name' => 'Admin HadirKu',
            'email' => 'admin@hadirku.id',
            'password' => Hash::make('hadirku123'),
        ]);
        $this->withToken($user->createToken('test')->plainTextToken);

        $this->postJson('/api/attendance', [
            'employeeId' => 'EMP-TEST',
            'type' => 'Pulang',
        ])->assertUnprocessable();

        $this->postJson('/api/attendance', [
            'employeeId' => 'EMP-TEST',
            'type' => 'Masuk',
        ])->assertCreated();

        $checkout = [
            'employeeId' => 'EMP-TEST',
            'type' => 'Pulang',
        ];

        $this->postJson('/api/attendance', $checkout)->assertCreated();
        $this->postJson('/api/attendance', $checkout)->assertUnprocessable();
    }

    public function test_employee_id_is_returned_using_frontend_api_shape(): void
    {
        Employee::query()->create([
            'employee_id' => 'EMP-TEST',
            'name' => 'Karyawan Tes',
            'department' => 'Produk',
            'active' => true,
        ]);

        $user = User::query()->create([
            'name' => 'Admin HadirKu',
            'email' => 'admin@hadirku.id',
            'password' => Hash::make('hadirku123'),
        ]);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/employees')
            ->assertOk()
            ->assertJsonPath('0.id', 'EMP-TEST');
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::query()->create([
            'name' => 'Admin HadirKu',
            'email' => 'admin@hadirku.id',
            'password' => Hash::make('hadirku123'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/dashboard')->assertUnauthorized();
    }
}
