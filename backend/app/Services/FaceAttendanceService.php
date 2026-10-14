<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\FaceAttempt;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class FaceAttendanceService
{
    public function __construct(
        private readonly AttendanceSettings $settings,
        private readonly LivenessChallenge $challenges,
    ) {}

    public function register(User $actor, Employee $employee, array $descriptor, array $liveness, string $ip): void
    {
        $this->authorizeEmployee($actor, $employee);
        $this->verifyLiveness($actor, $liveness, $ip, $employee->employee_id);

        $employee->faceProfile()->updateOrCreate(
            ['employee_id' => $employee->employee_id],
            [
                'descriptor' => $descriptor,
                'enrolled_at' => now(),
            ],
        );

        FaceAttempt::query()->create([
            'user_id' => $actor->id,
            'employee_id' => $employee->employee_id,
            'passed' => true,
            'reason' => 'enrollment',
            'ip_address' => $ip,
        ]);
        RateLimiter::clear('hadirku:face-failures:'.strtoupper($employee->employee_id));
    }

    public function record(
        User $actor,
        string $type,
        ?string $requestedEmployeeId,
        array $descriptor,
        UploadedFile $photo,
        float $latitude,
        float $longitude,
        array $liveness,
        string $ip,
    ): Attendance {
        $employeeId = $actor->role === 'admin' ? $requestedEmployeeId : $actor->username;
        $employee = Employee::query()->where('employee_id', $employeeId)->where('active', true)->first();

        if (! $employee || ($actor->role !== 'admin' && $employee->user_id !== $actor->id)) {
            $this->reject($actor, null, $ip, 'employee_not_found', 'Karyawan aktif tidak ditemukan.');
        }

        $this->assertAttemptsAllowed($employee->employee_id);
        $profile = $employee->faceProfile;

        if (! $profile) {
            $this->reject($actor, $employee->employee_id, $ip, 'face_not_registered', 'Data wajah belum didaftarkan.');
        }

        $this->verifyLiveness($actor, $liveness, $ip, $employee->employee_id);
        $distance = $this->distance($descriptor, $profile->descriptor);
        $settings = $this->settings->all();
        $threshold = (float) $settings['face_distance_threshold'];

        if ($distance > $threshold) {
            $this->reject($actor, $employee->employee_id, $ip, 'face_mismatch', 'Wajah tidak cocok dengan data terdaftar.');
        }

        $this->verifyLocation($latitude, $longitude, $settings, $actor, $employee->employee_id, $ip);

        $date = Carbon::now('Asia/Jakarta')->toDateString();
        $today = Attendance::query()->where('employee_id', $employee->employee_id)->whereDate('date', $date);

        if (($type === 'Masuk' && (clone $today)->where('type', 'Masuk')->exists())
            || ($type === 'Pulang' && ! (clone $today)->where('type', 'Masuk')->exists())
            || ($type === 'Pulang' && (clone $today)->where('type', 'Pulang')->exists())) {
            throw ValidationException::withMessages([
                'type' => [$type === 'Masuk'
                    ? 'Presensi masuk hari ini sudah tercatat.'
                    : 'Presensi pulang belum dapat dicatat atau sudah tercatat.'],
            ]);
        }

        $time = Carbon::now('Asia/Jakarta')->format('H:i');
        $status = $type === 'Masuk'
            ? ($time > $settings['late_after'] ? 'Terlambat' : 'Tepat waktu')
            : 'Selesai';
        $photoPath = $photo->store('attendance', 'local');

        try {
            $record = DB::transaction(function () use ($employee, $type, $date, $time, $status, $latitude, $longitude, $distance, $threshold, $photoPath): Attendance {
                return Attendance::query()->create([
                    'employee_id' => $employee->employee_id,
                    'type' => $type,
                    'date' => $date,
                    'time' => $time,
                    'status' => $status,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'face_distance' => $distance,
                    'match_score' => round(max(0, 100 * (1 - $distance)), 2),
                    'photo_path' => $photoPath,
                    'liveness_verified' => true,
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            Storage::disk('local')->delete($photoPath);

            throw ValidationException::withMessages([
                'type' => ['Presensi ini sudah tercatat.'],
            ]);
        }

        FaceAttempt::query()->create([
            'user_id' => $actor->id,
            'employee_id' => $employee->employee_id,
            'passed' => true,
            'reason' => 'attendance_'.$type,
            'ip_address' => $ip,
        ]);
        RateLimiter::clear('hadirku:face-failures:'.strtoupper($employee->employee_id));

        return $record;
    }

    private function verifyLiveness(User $actor, array $liveness, string $ip, string $employeeId): void
    {
        $challenge = $this->challenges->consume($liveness['challenge_id']);
        $events = $liveness['events'] ?? [];
        $valid = $challenge
            && $challenge['user_id'] === $actor->id
            && now()->timestamp - $challenge['issued_at'] >= 2
            && now()->timestamp - $challenge['issued_at'] <= 120
            && $liveness['elapsed_ms'] >= 1200
            && $liveness['elapsed_ms'] <= 30000;

        foreach ($challenge['actions'] ?? [] as $action) {
            $valid = $valid && ($events[$action] ?? false) === true;
        }

        if (! $valid) {
            $this->reject($actor, $employeeId, $ip, 'liveness_failed', 'Verifikasi liveness gagal atau tantangan kedaluwarsa.');
        }
    }

    private function verifyLocation(float $latitude, float $longitude, array $settings, User $actor, string $employeeId, string $ip): void
    {
        if (! $settings['geofence_enabled']) {
            return;
        }

        if ($settings['office_latitude'] === null || $settings['office_longitude'] === null) {
            $this->reject($actor, $employeeId, $ip, 'office_location_missing', 'Lokasi kantor belum dikonfigurasi.');
        }

        $distance = $this->earthDistance(
            $latitude,
            $longitude,
            (float) $settings['office_latitude'],
            (float) $settings['office_longitude'],
        );

        if ($distance > (float) $settings['geofence_radius_meters']) {
            $this->reject($actor, $employeeId, $ip, 'outside_geofence', 'Anda berada di luar jangkauan lokasi kantor.');
        }
    }

    private function assertAttemptsAllowed(string $employeeId): void
    {
        $limit = max(1, (int) $this->settings->all()['face_failure_limit']);
        $window = max(1, (int) $this->settings->all()['face_failure_window_minutes']);
        $key = 'hadirku:face-failures:'.strtoupper($employeeId);

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            throw new TooManyRequestsHttpException(
                RateLimiter::availableIn($key),
                'Terlalu banyak percobaan verifikasi wajah. Coba kembali nanti.',
            );
        }
    }

    private function reject(User $actor, ?string $employeeId, string $ip, string $reason, string $message): never
    {
        FaceAttempt::query()->create([
            'user_id' => $actor->id,
            'employee_id' => $employeeId,
            'passed' => false,
            'reason' => $reason,
            'ip_address' => $ip,
        ]);

        if ($employeeId !== null) {
            $settings = $this->settings->all();
            RateLimiter::hit(
                'hadirku:face-failures:'.strtoupper($employeeId),
                max(1, (int) $settings['face_failure_window_minutes']) * 60,
            );
        }

        throw ValidationException::withMessages(['face' => [$message]]);
    }

    private function authorizeEmployee(User $actor, Employee $employee): void
    {
        abort_unless(
            $actor->role === 'admin' || ($actor->role === 'karyawan' && $employee->user_id === $actor->id),
            403,
        );

        if (! $employee->active) {
            throw ValidationException::withMessages(['employee_id' => ['Akun karyawan tidak aktif.']]);
        }
    }

    private function distance(array $first, array $second): float
    {
        $sum = 0.0;
        for ($index = 0; $index < 128; $index++) {
            $sum += ($first[$index] - $second[$index]) ** 2;
        }

        return sqrt($sum);
    }

    private function earthDistance(float $latitude, float $longitude, float $officeLatitude, float $officeLongitude): float
    {
        $radius = 6371000;
        $deltaLatitude = deg2rad($officeLatitude - $latitude);
        $deltaLongitude = deg2rad($officeLongitude - $longitude);
        $a = sin($deltaLatitude / 2) ** 2
            + cos(deg2rad($latitude)) * cos(deg2rad($officeLatitude)) * sin($deltaLongitude / 2) ** 2;

        return $radius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
