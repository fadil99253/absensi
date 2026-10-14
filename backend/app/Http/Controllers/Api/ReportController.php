<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Services\AttendanceExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);
        $records = $this->records($filters)->paginate($filters['per_page'] ?? 50);

        return response()->json([
            'success' => true,
            'message' => 'Rekap presensi.',
            'data' => $records,
        ]);
    }

    public function export(Request $request, string $format, AttendanceExportService $exports): Response
    {
        $filters = $this->validateFilters($request);
        $records = $this->records($filters)->get();
        $from = $filters['date_from'] ?? $filters['date'] ?? Carbon::now('Asia/Jakarta')->toDateString();
        $to = $filters['date_to'] ?? $filters['date'] ?? $from;

        if ($format === 'xlsx') {
            $path = $exports->xlsx($records);

            return response()->download($path, 'rekap-presensi-'.$from.'-'.$to.'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        }

        return response($exports->pdf($records, $from, $to), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="rekap-presensi-'.$from.'-'.$to.'.pdf"',
        ]);
    }

    private function validateFilters(Request $request): array
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

        if (isset($validated['date_from'], $validated['date_to'])
            && $validated['date_to'] < $validated['date_from']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'date_to' => ['Tanggal akhir harus sama atau setelah tanggal mulai.'],
            ]);
        }

        return $validated;
    }

    private function records(array $filters)
    {
        return Attendance::query()
            ->with('employee')
            ->when($filters['date'] ?? null, fn ($query, string $date) => $query->whereDate('date', $date))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('date', '<=', $date))
            ->when($filters['employee_id'] ?? null, fn ($query, string $id) => $query->where('employee_id', strtoupper($id)))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->whereHas('employee', function ($employeeQuery) use ($search): void {
                    $employeeQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%");
                });
            })
            ->latest('date')->latest('time');
    }
}
