<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $activeEmployees = Employee::query()->where('active', true);
        $total = (clone $activeEmployees)->count();
        $present = Attendance::query()
            ->whereDate('date', $today)
            ->where('type', 'Masuk')
            ->distinct('employee_id')
            ->count('employee_id');
        $late = Attendance::query()
            ->whereDate('date', $today)
            ->where('type', 'Masuk')
            ->where('status', 'Terlambat')
            ->count();
        $recent = Attendance::query()
            ->with('employee')
            ->whereDate('date', $today)
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (Attendance $record): array => $this->formatRecord($record));

        return response()->json([
            'date' => $today,
            'counts' => [
                'present' => $present,
                'late' => $late,
                'absent' => max($total - $present, 0),
                'total' => $total,
            ],
            'recent' => $recent,
            'employees' => (clone $activeEmployees)->get(),
        ]);
    }

    private function formatRecord(Attendance $record): array
    {
        return [
            'id' => $record->id,
            'employeeId' => $record->employee_id,
            'employeeName' => $record->employee?->name ?? 'Karyawan tidak aktif',
            'department' => $record->employee?->department ?? '-',
            'type' => $record->type,
            'date' => $record->date->toDateString(),
            'time' => $record->time,
            'status' => $record->status,
        ];
    }
}
