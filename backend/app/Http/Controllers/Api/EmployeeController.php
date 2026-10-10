<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Employee::query()->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        if (is_string($request->input('id'))) {
            $request->merge(['id' => strtoupper(trim($request->input('id')))]);
        }

        $validated = $request->validate([
            'id' => ['required', 'string', 'min:2', 'max:24', 'regex:/^[a-zA-Z0-9-]+$/', Rule::unique('employees', 'employee_id')],
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'department' => ['required', 'string', 'min:2', 'max:60'],
            'role' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:120'],
        ]);

        $employee = Employee::query()->create([
            'employee_id' => strtoupper($validated['id']),
            'name' => trim($validated['name']),
            'department' => trim($validated['department']),
            'role' => isset($validated['role']) && trim($validated['role']) !== ''
                ? trim($validated['role'])
                : 'Karyawan',
            'email' => isset($validated['email']) ? trim($validated['email']) : null,
            'active' => true,
        ]);

        return response()->json($employee, 201);
    }
}
