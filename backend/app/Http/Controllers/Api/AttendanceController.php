<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);
        $date = $validated['date'] ?? Carbon::now('Asia/Jakarta')->toDateString();

        $records = Attendance::query()
            ->with('employee')
            ->whereDate('date', $date)
            ->when($validated['q'] ?? null, function ($query, string $search): void {
                $search = trim($search);
                if ($search === '') {
                    return;
                }

                $query->whereHas('employee', function ($employeeQuery) use ($search): void {
                    $employeeQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->get()
            ->map(fn (Attendance $record): array => $this->formatRecord($record));

        return response()->json(['date' => $date, 'records' => $records]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employeeId' => ['required', 'string', 'regex:/^[a-zA-Z0-9-]{2,24}$/'],
            'type' => ['required', 'in:Masuk,Pulang'],
        ]);

        $record = DB::transaction(function () use ($validated): Attendance {
            $employee = Employee::query()
                ->where('employee_id', $validated['employeeId'])
                ->where('active', true)
                ->first();

            if (! $employee) {
                throw ValidationException::withMessages([
                    'employeeId' => ['Karyawan aktif tidak ditemukan.'],
                ]);
            }

            $date = Carbon::now('Asia/Jakarta')->toDateString();
            $type = $validated['type'];
            $todayRecords = Attendance::query()
                ->where('employee_id', $employee->employee_id)
                ->whereDate('date', $date);

            if ($type === 'Masuk' && (clone $todayRecords)->where('type', 'Masuk')->exists()) {
                throw ValidationException::withMessages([
                    'type' => ['Presensi masuk hari ini sudah tercatat.'],
                ]);
            }

            if ($type === 'Pulang' && ! (clone $todayRecords)->where('type', 'Masuk')->exists()) {
                throw ValidationException::withMessages([
                    'type' => ['Presensi masuk harus dilakukan sebelum presensi pulang.'],
                ]);
            }

            if ($type === 'Pulang' && (clone $todayRecords)->where('type', 'Pulang')->exists()) {
                throw ValidationException::withMessages([
                    'type' => ['Presensi pulang hari ini sudah tercatat.'],
                ]);
            }

            $time = Carbon::now('Asia/Jakarta')->format('H:i');

            return Attendance::query()->create([
                'employee_id' => $employee->employee_id,
                'type' => $type,
                'date' => $date,
                'time' => $time,
                'status' => $type === 'Masuk' && $time > '09:00' ? 'Terlambat' : 'Tepat waktu',
            ]);
        });

        return response()->json($this->formatRecord($record->load('employee')), 201);
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
