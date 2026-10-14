<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AttendanceSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function show(AttendanceSettings $settings): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Pengaturan sistem.',
            'data' => $settings->all(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'work_start' => ['required', 'date_format:H:i'],
            'late_after' => ['required', 'date_format:H:i'],
            'work_end' => ['required', 'date_format:H:i', 'after:work_start'],
            'geofence_enabled' => ['required', 'boolean'],
            'office_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'office_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geofence_radius_meters' => ['required', 'integer', 'between:10,5000'],
            'face_distance_threshold' => ['required', 'numeric', 'between:0.1,1'],
            'face_failure_limit' => ['required', 'integer', 'between:1,10'],
            'face_failure_window_minutes' => ['required', 'integer', 'between:1,60'],
        ]);

        if ($validated['late_after'] < $validated['work_start'] || $validated['late_after'] > $validated['work_end']) {
            throw ValidationException::withMessages([
                'late_after' => ['Batas keterlambatan harus berada di dalam jam operasional.'],
            ]);
        }

        if ($validated['geofence_enabled']
            && ($validated['office_latitude'] === null || $validated['office_longitude'] === null)) {
            throw ValidationException::withMessages([
                'office_latitude' => ['Koordinat kantor wajib diisi jika geofencing diaktifkan.'],
            ]);
        }

        foreach ($validated as $key => $value) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }

        return $this->show(app(AttendanceSettings::class));
    }
}
