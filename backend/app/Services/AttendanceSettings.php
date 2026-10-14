<?php

namespace App\Services;

use App\Models\SystemSetting;

class AttendanceSettings
{
    public function all(): array
    {
        $settings = SystemSetting::query()
            ->get()
            ->mapWithKeys(fn (SystemSetting $setting): array => [$setting->key => $setting->value])
            ->all();

        return array_replace([
            'work_start' => '08:00',
            'late_after' => '09:00',
            'work_end' => '17:00',
            'geofence_enabled' => false,
            'office_latitude' => null,
            'office_longitude' => null,
            'geofence_radius_meters' => 150,
            'face_distance_threshold' => 0.5,
            'face_failure_limit' => 5,
            'face_failure_window_minutes' => 10,
        ], $settings);
    }
}
