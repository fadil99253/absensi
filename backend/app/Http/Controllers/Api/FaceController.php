<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\FaceAttendanceService;
use App\Services\LivenessChallenge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaceController extends Controller
{
    public function challenge(Request $request, LivenessChallenge $challenges): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Tantangan liveness siap.',
            'data' => $challenges->issue($request->user()->id),
        ]);
    }

    public function register(Request $request, Employee $employee, FaceAttendanceService $attendance): JsonResponse
    {
        $validated = $request->validate([
            'descriptor' => ['required', 'array', 'size:128'],
            'descriptor.*' => ['required', 'numeric', 'between:-1,1'],
            'liveness.challenge_id' => ['required', 'string', 'size:48'],
            'liveness.events' => ['required', 'array'],
            'liveness.events.*' => ['required', 'boolean'],
            'liveness.elapsed_ms' => ['required', 'integer', 'between:1200,30000'],
        ]);

        $attendance->register(
            $request->user(),
            $employee,
            array_map('floatval', array_values($validated['descriptor'])),
            $validated['liveness'],
            $request->ip(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Data wajah berhasil didaftarkan.',
            'data' => ['employee_id' => $employee->employee_id, 'face_registered' => true],
        ]);
    }
}
