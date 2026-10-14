<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Services\AttendanceSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AttendanceSettings $settings): JsonResponse
    {
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $user = $request->user();

        if ($user->role !== 'admin') {
            $monthStart = Carbon::now('Asia/Jakarta')->startOfMonth()->toDateString();
            $records = Attendance::query()
                ->where('employee_id', $user->username)
                ->whereDate('date', $today)
                ->orderBy('time')
                ->get();
            $monthRecords = Attendance::query()->where('employee_id', $user->username)
                ->whereDate('date', '>=', $monthStart)
                ->where('type', 'Masuk');

            return response()->json([
                'success' => true,
                'message' => 'Ringkasan presensi karyawan.',
                'data' => [
                    'date' => $today,
                    'user' => [
                        'name' => $user->name,
                        'username' => $user->username,
                        'department' => $user->employee?->department,
                        'face_registered' => $user->employee?->faceProfile()->exists() ?? false,
                    ],
                    'today' => [
                        'check_in' => $records->firstWhere('type', 'Masuk')?->time,
                        'check_out' => $records->firstWhere('type', 'Pulang')?->time,
                        'status' => $records->firstWhere('type', 'Masuk')?->status ?? 'Belum hadir',
                    ],
                    'stats' => [
                        'present_this_month' => (clone $monthRecords)->count(),
                        'late_this_month' => (clone $monthRecords)->where('status', 'Terlambat')->count(),
                    ],
                    'recent' => $this->recentRecords($user->username),
                    'settings' => $settings->all(),
                ],
            ]);
        }

        $total = Employee::query()->where('active', true)->count();
        $todayAttendance = Attendance::query()->whereDate('date', $today)->where('type', 'Masuk');
        $present = (clone $todayAttendance)->distinct('employee_id')->count('employee_id');
        $late = (clone $todayAttendance)->where('status', 'Terlambat')->count();
        $trend = [];

        for ($offset = 6; $offset >= 0; $offset--) {
            $date = Carbon::now('Asia/Jakarta')->subDays($offset);
            $trend[] = [
                'date' => $date->toDateString(),
                'label' => $date->translatedFormat('D'),
                'present' => Attendance::query()->whereDate('date', $date->toDateString())
                    ->where('type', 'Masuk')->distinct('employee_id')->count('employee_id'),
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Ringkasan dashboard administrator.',
            'data' => [
                'date' => $today,
                'counts' => [
                    'total' => $total,
                    'present' => $present,
                    'late' => $late,
                    'absent' => max(0, $total - $present),
                ],
                'trend' => $trend,
                'recent' => $this->recentRecords(),
                'settings' => $settings->all(),
            ],
        ]);
    }

    private function recentRecords(?string $employeeId = null): array
    {
        return Attendance::query()
            ->with('employee')
            ->when($employeeId, fn ($query, string $id) => $query->where('employee_id', $id))
            ->latest('date')->latest('time')->limit(8)
            ->get()
            ->map(fn (Attendance $record): array => [
                'id' => $record->id,
                'employee_id' => $record->employee_id,
                'employee_name' => $record->employee?->name ?? 'Karyawan tidak aktif',
                'department' => $record->employee?->department ?? '-',
                'date' => $record->date->toDateString(),
                'time' => $record->time,
                'type' => $record->type,
                'status' => $record->status,
                'match_score' => $record->match_score,
            ])->all();
    }
}
