<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:80'],
            'active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $employees = Employee::query()
            ->with('faceProfile')
            ->withCount('attendanceRecords')
            ->when($validated['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($validated['department'] ?? null, fn ($query, string $department) => $query->where('department', $department))
            ->when(array_key_exists('active', $validated), fn ($query) => $query->where('active', $validated['active']))
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 50)
            ->through(fn (Employee $employee): array => $this->formatEmployee($employee));

        return response()->json([
            'success' => true,
            'message' => 'Data karyawan.',
            'data' => $employees,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'string', 'min:2', 'max:32', 'regex:/^[a-zA-Z0-9.-]+$/', 'unique:employees,employee_id', 'unique:users,username'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:191', 'unique:users,email'],
            'department' => ['required', 'string', 'max:80'],
            'position' => ['nullable', 'string', 'max:80'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        $employee = DB::transaction(function () use ($validated): Employee {
            $user = User::query()->create([
                'name' => trim($validated['name']),
                'username' => strtoupper(trim($validated['employee_id'])),
                'email' => strtolower(trim($validated['email'])),
                'password' => $validated['password'],
                'role' => 'karyawan',
            ]);

            return Employee::query()->create([
                'employee_id' => $user->username,
                'user_id' => $user->id,
                'name' => $user->name,
                'role' => trim($validated['position'] ?? '') ?: 'Karyawan',
                'department' => trim($validated['department']),
                'email' => $user->email,
                'active' => true,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Akun dan data karyawan berhasil dibuat.',
            'data' => $this->formatEmployee($employee),
        ], 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail karyawan.',
            'data' => $this->formatEmployee($employee->load('faceProfile')->loadCount('attendanceRecords')),
        ]);
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'email' => [
                'sometimes',
                'required',
                'email:rfc',
                'max:191',
                Rule::unique('employees', 'email')->ignore($employee->employee_id, 'employee_id'),
                Rule::unique('users', 'email')->ignore($employee->user_id),
            ],
            'department' => ['sometimes', 'required', 'string', 'max:80'],
            'position' => ['sometimes', 'required', 'string', 'max:80'],
            'active' => ['sometimes', 'required', 'boolean'],
        ]);

        DB::transaction(function () use ($employee, $validated): void {
            $active = array_key_exists('active', $validated) ? (bool) $validated['active'] : null;
            $employee->update([
                ...array_filter([
                    'name' => isset($validated['name']) ? trim($validated['name']) : null,
                    'email' => isset($validated['email']) ? strtolower(trim($validated['email'])) : null,
                    'department' => isset($validated['department']) ? trim($validated['department']) : null,
                    'role' => isset($validated['position']) ? trim($validated['position']) : null,
                    'active' => $active,
                ], fn ($value) => $value !== null),
            ]);

            $employee->user?->update(array_filter([
                'name' => $validated['name'] ?? null,
                'email' => $validated['email'] ?? null,
            ], fn ($value) => $value !== null));

            if ($active === false) {
                $employee->user?->tokens()->delete();
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Data karyawan diperbarui.',
            'data' => $this->formatEmployee($employee->fresh()->load('faceProfile')->loadCount('attendanceRecords')),
        ]);
    }

    public function destroy(Employee $employee): JsonResponse
    {
        DB::transaction(function () use ($employee): void {
            $employee->update(['active' => false]);
            $employee->user?->tokens()->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Akun karyawan dinonaktifkan; riwayat presensi tetap tersimpan.',
            'data' => null,
        ]);
    }

    private function formatEmployee(Employee $employee): array
    {
        return [
            'employee_id' => $employee->employee_id,
            'name' => $employee->name,
            'position' => $employee->role,
            'department' => $employee->department,
            'email' => $employee->email,
            'active' => $employee->active,
            'face_registered' => $employee->faceProfile !== null,
            'attendance_count' => $employee->attendance_records_count ?? null,
            'created_at' => $employee->created_at?->toISOString(),
        ];
    }
}
