<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'employee_id' => ['nullable', 'string', 'max:32'],
            'type' => ['nullable', Rule::in(['Masuk', 'Pulang'])],
            'status' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $this->validateDateRange($validated);

        $user = $request->user();
        $query = Attendance::query()->with('employee');

        if ($user->role !== 'admin') {
            $query->where('employee_id', $user->username);
        } elseif (! empty($validated['employee_id'])) {
            $query->where('employee_id', strtoupper($validated['employee_id']));
        }

        $query
            ->when($validated['date'] ?? null, fn ($builder, string $date) => $builder->whereDate('date', $date))
            ->when($validated['date_from'] ?? null, fn ($builder, string $date) => $builder->whereDate('date', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($builder, string $date) => $builder->whereDate('date', '<=', $date))
            ->when($validated['type'] ?? null, fn ($builder, string $type) => $builder->where('type', $type))
            ->when($validated['status'] ?? null, fn ($builder, string $status) => $builder->where('status', $status))
            ->when($validated['q'] ?? null, function ($builder, string $search): void {
                $search = trim($search);
                $builder->whereHas('employee', function ($employeeQuery) use ($search): void {
                    $employeeQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%");
                });
            });

        $records = $query->latest('date')->latest('time')
            ->paginate($validated['per_page'] ?? 30)
            ->through(fn (Attendance $record): array => $this->formatRecord($record));

        return response()->json([
            'success' => true,
            'message' => 'Riwayat presensi.',
            'data' => $records,
        ]);
    }

    public function store(Request $request, \App\Services\FaceAttendanceService $attendance): JsonResponse
    {
        $isAdmin = $request->user()->role === 'admin';
        $validated = $request->validate([
            'employee_id' => [$isAdmin ? 'required' : 'nullable', 'string', 'max:32'],
            'type' => ['required', Rule::in(['Masuk', 'Pulang'])],
            'descriptor' => ['required', 'array', 'size:128'],
            'descriptor.*' => ['required', 'numeric', 'between:-1,1'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'liveness.challenge_id' => ['required', 'string', 'size:48'],
            'liveness.events' => ['required', 'array'],
            'liveness.events.*' => ['required', 'boolean'],
            'liveness.elapsed_ms' => ['required', 'integer', 'between:1200,30000'],
        ]);

        $record = $attendance->record(
            $request->user(),
            $validated['type'],
            isset($validated['employee_id']) ? strtoupper($validated['employee_id']) : null,
            array_map('floatval', array_values($validated['descriptor'])),
            $validated['photo'],
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            $validated['liveness'],
            $request->ip(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Presensi berhasil diverifikasi dan dicatat.',
            'data' => $this->formatRecord($record->load('employee')),
        ], 201);
    }

    private function formatRecord(Attendance $record): array
    {
        return [
            'id' => $record->id,
            'employee_id' => $record->employee_id,
            'employee_name' => $record->employee?->name ?? 'Karyawan tidak aktif',
            'department' => $record->employee?->department ?? '-',
            'type' => $record->type,
            'date' => $record->date->toDateString(),
            'time' => $record->time,
            'status' => $record->status,
            'match_score' => $record->match_score,
            'latitude' => $record->latitude,
            'longitude' => $record->longitude,
            'liveness_verified' => $record->liveness_verified,
        ];
    }

    private function validateDateRange(array $validated): void
    {
        if (isset($validated['date_from'], $validated['date_to'])
            && $validated['date_to'] < $validated['date_from']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'date_to' => ['Tanggal akhir harus sama atau setelah tanggal mulai.'],
            ]);
        }
    }
}
