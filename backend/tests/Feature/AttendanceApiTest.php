<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeFaceProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_persisted_employee_account_and_nip_login_token(): void
    {
        $this->postJson('/api/register', [
            'nip' => 'KRY-0001',
            'name' => 'Karyawan Pertama',
            'email' => 'pertama@example.test',
            'department' => 'Operasional',
            'position' => 'Analis',
            'password' => 'password-yang-aman',
            'password_confirmation' => 'password-yang-aman',
            'role' => 'admin',
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', 'karyawan')
            ->assertJsonPath('data.user.username', 'KRY-0001')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'employee_id']]]);

        $this->assertDatabaseHas('users', [
            'username' => 'KRY-0001',
            'email' => 'pertama@example.test',
            'role' => 'karyawan',
        ]);
        $this->assertDatabaseHas('employees', [
            'employee_id' => 'KRY-0001',
            'department' => 'Operasional',
            'active' => true,
        ]);

        $this->postJson('/api/login', [
            'username' => 'kry-0001',
            'password' => 'password-yang-aman',
        ])->assertOk()
            ->assertJsonPath('data.user.username', 'KRY-0001')
            ->assertJsonPath('data.user.role', 'karyawan')
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_forgot_password_sends_reset_notification_without_revealing_unknown_accounts(): void
    {
        Notification::fake();
        $user = User::query()->create([
            'name' => 'Karyawan Reset',
            'username' => 'RESET-1',
            'email' => 'reset@example.test',
            'password' => 'password-yang-aman',
            'role' => 'karyawan',
        ]);

        $this->postJson('/api/password/forgot', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Jika email terdaftar, tautan pemulihan akan dikirim.');

        Notification::assertSentTo($user, ResetPassword::class);

        $this->postJson('/api/password/forgot', ['email' => 'missing@example.test'])
            ->assertOk()
            ->assertJsonPath('message', 'Jika email terdaftar, tautan pemulihan akan dikirim.');
    }

    public function test_registration_validation_uses_consistent_json_error_shape(): void
    {
        $this->post('/api/register', [
            'nip' => 'invalid!',
            'name' => 'A',
            'email' => 'not-an-email',
            'department' => '',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors']);
    }

    public function test_registration_validation_messages_are_translated_to_indonesian(): void
    {
        $this->postJson('/api/register', [
            'nip' => 'abyan',
            'name' => 'Nama Karyawan',
            'email' => 'abyan@example.test',
            'department' => 'Operasional',
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'kata sandi minimal 10 karakter.')
            ->assertJsonMissing(['message' => 'validation.min.string']);
    }

    public function test_seed_creates_only_one_admin_with_env_configured_password(): void
    {
        config([
            'database.connections.sqlite.database' => ':memory:',
        ]);
        config([
            'hadirku.admin_initial_username' => 'admin-test',
            'hadirku.admin_initial_email' => 'admin-test@example.test',
            'hadirku.admin_initial_password' => 'change-this-admin-password',
        ]);

        $this->seed();

        $admin = User::query()->where('email', 'admin-test@example.test')->firstOrFail();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('change-this-admin-password', $admin->password));
        $this->assertSame(1, User::query()->count());
        $this->assertSame(0, Employee::query()->count());
    }

    public function test_employee_account_cannot_access_admin_employee_directory(): void
    {
        $employee = $this->makeEmployeeAccount();
        $token = $employee->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/employees')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_dashboard_returns_role_specific_payloads_for_employee_and_admin(): void
    {
        $employee = $this->makeEmployeeAccount();
        $employeeToken = $employee->createToken('employee-test')->plainTextToken;
        $admin = User::query()->create([
            'name' => 'Administrator',
            'username' => 'admin-test',
            'email' => 'admin@example.test',
            'password' => 'password-yang-aman',
            'role' => 'admin',
        ]);
        $adminToken = $admin->createToken('admin-test')->plainTextToken;

        $this->getJson('/api/dashboard', ['Authorization' => 'Bearer '.$employeeToken])
            ->assertOk()
            ->assertJsonPath('data.user.username', 'KRY-0001')
            ->assertJsonStructure(['data' => ['today', 'stats', 'recent']])
            ->assertJsonMissingPath('data.counts')
            ->assertJsonMissingPath('data.trend');

        Auth::forgetGuards();

        $this->getJson('/api/dashboard', ['Authorization' => 'Bearer '.$adminToken])
            ->assertOk()
            ->assertJsonPath('message', 'Ringkasan dashboard administrator.')
            ->assertJsonStructure(['data' => ['counts', 'trend', 'recent']])
            ->assertJsonMissingPath('data.user')
            ->assertJsonMissingPath('data.today');
    }

    public function test_api_returns_json_without_browser_accept_header(): void
    {
        $this->get('/api/dashboard')
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonStructure(['success', 'message', 'errors']);
    }

    public function test_backend_root_and_health_check_are_json_without_a_ui(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('data.api', '/api');

        $this->get('/up')
            ->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('data.status', 'ok');

        $this->get('/api')
        ->assertNotFound()
        ->assertHeader('content-type', 'application/json')
        ->assertJsonStructure(['success', 'message', 'errors']);
    }

    public function test_frontend_origins_are_allowed_by_cors_for_json_api_requests(): void
    {
        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'authorization,content-type',
        ])->options('/api/login')
            ->assertNoContent()
            ->assertHeader('access-control-allow-origin', 'http://localhost:5173');
    }

    public function test_employee_can_enroll_encrypted_face_descriptor_with_liveness_challenge(): void
    {
        $employeeUser = $this->makeEmployeeAccount();
        $token = $employeeUser->createToken('test')->plainTextToken;
        $challenge = $this->postJson('/api/face/challenge', [], ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->json('data');

        $this->travel(3)->seconds();
        $response = $this->withToken($token)->postJson('/api/employees/KRY-0001/face', [
            'descriptor' => $this->descriptor(),
            'liveness' => [
                'challenge_id' => $challenge['challenge_id'],
                'events' => array_fill_keys($challenge['actions'], true),
                'elapsed_ms' => 3000,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.face_registered', true);

        $stored = EmployeeFaceProfile::query()->firstOrFail();
        $this->assertIsArray($stored->descriptor);
        $this->assertCount(128, $stored->descriptor);
        $this->assertStringNotContainsString('0.05', $stored->getRawOriginal('descriptor'));
        $this->assertDatabaseHas('face_attempts', ['passed' => true, 'reason' => 'enrollment']);
    }

    public function test_admin_can_create_employee_account_and_enroll_the_employees_face(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Tes',
            'username' => 'ADMIN-TEST',
            'email' => 'admin-test@example.test',
            'password' => 'password-yang-aman',
            'role' => 'admin',
        ]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/employees', [
            'employee_id' => 'KRY-0002',
            'name' => 'Pegawai Baru',
            'email' => 'pegawai-baru@example.test',
            'department' => 'Operasional',
            'position' => 'Analis',
            'password' => 'password-yang-aman',
            'password_confirmation' => 'password-yang-aman',
        ])->assertCreated()
            ->assertJsonPath('data.employee_id', 'KRY-0002')
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.face_registered', false);

        $challenge = $this->withToken($token)->postJson('/api/face/challenge', [])->assertOk()->json('data');
        $this->travel(3)->seconds();

        $this->withToken($token)->postJson('/api/employees/KRY-0002/face', [
            'descriptor' => $this->descriptor(),
            'liveness' => [
                'challenge_id' => $challenge['challenge_id'],
                'events' => array_fill_keys($challenge['actions'], true),
                'elapsed_ms' => 3000,
            ],
        ])->assertOk()
            ->assertJsonPath('data.employee_id', 'KRY-0002')
            ->assertJsonPath('data.face_registered', true);

        $this->assertDatabaseHas('users', ['username' => 'KRY-0002', 'role' => 'karyawan']);
        $this->assertDatabaseHas('employees', ['employee_id' => 'KRY-0002', 'active' => true]);
        $this->assertDatabaseHas('employee_face_profiles', ['employee_id' => 'KRY-0002']);
    }

    public function test_employee_must_complete_the_server_issued_liveness_challenge(): void
    {
        $employeeUser = $this->makeEmployeeAccount();
        EmployeeFaceProfile::query()->create([
            'employee_id' => 'KRY-0001',
            'descriptor' => $this->descriptor(),
            'enrolled_at' => now(),
        ]);
        $token = $employeeUser->createToken('test')->plainTextToken;
        $challenge = $this->postJson('/api/face/challenge', [], ['Authorization' => 'Bearer '.$token])->json('data');
        $this->travel(3)->seconds();

        $this->withToken($token)->postJson('/api/employees/KRY-0001/face', [
            'descriptor' => $this->descriptor(),
            'liveness' => [
                'challenge_id' => $challenge['challenge_id'],
                'events' => array_fill_keys($challenge['actions'], false),
                'elapsed_ms' => 3000,
            ],
        ])->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('face_attempts', ['passed' => false, 'reason' => 'liveness_failed']);
    }

    public function test_face_attendance_rejects_a_descriptor_outside_the_configured_threshold(): void
    {
        Storage::fake('local');
        $employeeUser = $this->makeEmployeeAccount();
        EmployeeFaceProfile::query()->create([
            'employee_id' => 'KRY-0001',
            'descriptor' => $this->descriptor(),
            'enrolled_at' => now(),
        ]);
        $token = $employeeUser->createToken('test')->plainTextToken;
        $challenge = $this->postJson('/api/face/challenge', [], ['Authorization' => 'Bearer '.$token])->json('data');
        $this->travel(3)->seconds();

        $descriptor = $this->descriptor();
        $descriptor[0] = 0.95;

        $this->withToken($token)->post('/api/attendance', [
            'type' => 'Masuk',
            'descriptor' => $descriptor,
            'photo' => UploadedFile::fake()->create('evidence.jpg', 2, 'image/jpeg'),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'liveness' => [
                'challenge_id' => $challenge['challenge_id'],
                'events' => array_fill_keys($challenge['actions'], true),
                'elapsed_ms' => 3000,
            ],
        ], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonPath('errors.face.0', 'Wajah tidak cocok dengan data terdaftar.');

        $this->assertSame(0, Attendance::query()->count());
        $this->assertDatabaseHas('face_attempts', ['passed' => false, 'reason' => 'face_mismatch']);
    }

    public function test_face_attendance_stores_location_photo_and_match_score_after_verification(): void
    {
        Storage::fake('local');
        $employeeUser = $this->makeEmployeeAccount();
        EmployeeFaceProfile::query()->create([
            'employee_id' => 'KRY-0001',
            'descriptor' => $this->descriptor(),
            'enrolled_at' => now(),
        ]);
        $token = $employeeUser->createToken('test')->plainTextToken;
        $challenge = $this->postJson('/api/face/challenge', [], ['Authorization' => 'Bearer '.$token])->json('data');
        $this->travel(3)->seconds();

        $response = $this->withToken($token)->post('/api/attendance', [
            'type' => 'Masuk',
            'descriptor' => $this->descriptor(),
            'photo' => UploadedFile::fake()->create('evidence.jpg', 2, 'image/jpeg'),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'liveness' => [
                'challenge_id' => $challenge['challenge_id'],
                'events' => array_fill_keys($challenge['actions'], true),
                'elapsed_ms' => 3000,
            ],
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.employee_id', 'KRY-0001')
            ->assertJsonPath('data.match_score', 100)
            ->assertJsonPath('data.liveness_verified', true);
        $record = Attendance::query()->firstOrFail();
        $this->assertSame(-6.2, $record->latitude);
        Storage::disk('local')->assertExists($record->photo_path);
    }

    public function test_admin_can_change_geofence_and_face_threshold_settings(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Test',
            'username' => 'ADMIN',
            'email' => 'admin@example.test',
            'password' => 'secure-password',
            'role' => 'admin',
        ]);

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->putJson('/api/settings', [
                'work_start' => '08:30',
                'late_after' => '09:00',
                'work_end' => '17:30',
                'geofence_enabled' => true,
                'office_latitude' => -6.2,
                'office_longitude' => 106.8,
                'geofence_radius_meters' => 120,
                'face_distance_threshold' => 0.42,
                'face_failure_limit' => 4,
                'face_failure_window_minutes' => 12,
            ])->assertOk()
            ->assertJsonPath('data.geofence_enabled', true)
            ->assertJsonPath('data.face_distance_threshold', 0.42);
    }

    public function test_attendance_exports_are_valid_pdf_and_xlsx_documents(): void
    {
        $employeeUser = $this->makeEmployeeAccount();
        Attendance::query()->create([
            'employee_id' => 'KRY-0001',
            'date' => now()->toDateString(),
            'time' => '08:30',
            'type' => 'Masuk',
            'status' => 'Tepat waktu',
            'match_score' => 99.5,
        ]);
        $admin = User::query()->create([
            'name' => 'Admin Tes',
            'username' => 'ADMIN-TEST',
            'email' => 'export-admin@example.test',
            'password' => 'secure-password',
            'role' => 'admin',
        ]);

        $pdf = $this->withToken($admin->createToken('export')->plainTextToken)
            ->get('/api/reports/attendance/export/pdf?date='.now()->toDateString(), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());

        $xlsx = $this->withToken($admin->createToken('export')->plainTextToken)
            ->get('/api/reports/attendance/export/xlsx?date='.now()->toDateString(), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $file = $xlsx->baseResponse->getFile()->getPathname();
        $archive = new \ZipArchive();
        $this->assertTrue($archive->open($file));
        $sheet = $archive->getFromName('xl/worksheets/sheet1.xml');
        $archive->close();
        $this->assertIsString($sheet);
        $this->assertStringContainsString('Karyawan Satu', $sheet);
    }

    private function makeEmployeeAccount(): User
    {
        $user = User::query()->create([
            'name' => 'Karyawan Satu',
            'username' => 'KRY-0001',
            'email' => 'karyawan@example.test',
            'password' => 'password-yang-aman',
            'role' => 'karyawan',
        ]);

        Employee::query()->create([
            'employee_id' => 'KRY-0001',
            'user_id' => $user->id,
            'name' => $user->name,
            'role' => 'Analis',
            'department' => 'Operasional',
            'email' => $user->email,
            'active' => true,
        ]);

        return $user;
    }

    private function descriptor(): array
    {
        return array_fill(0, 128, 0.05);
    }
}
