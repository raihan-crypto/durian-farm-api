<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use ApiResponse;

    /**
     * Read-only listing of immutable audit logs (SRS Bab 17).
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()->with('user:id,name,email');

        if ($request->filled('table_name')) {
            $query->where('table_name', $request->string('table_name')->toString());
        }

        if ($request->filled('action')) {
            $query->where('action', $request->string('action')->toString());
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->string('user_id')->toString());
        }

        if ($request->filled('record_id')) {
            $query->where('record_id', $request->string('record_id')->toString());
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->string('from_date')->toString());
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->string('to_date')->toString());
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $logs = $query->orderByDesc('id')->paginate($perPage);

        return $this->successResponse([
            'items' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ], 'Riwayat audit log berhasil dimuat');
    }

    /**
     * Display a single audit log entry.
     */
    public function show(int $id): JsonResponse
    {
        $log = AuditLog::query()->with('user:id,name,email')->find($id);

        if (! $log) {
            return $this->errorResponse('Audit log tidak ditemukan', 404);
        }

        return $this->successResponse($log, 'Detail audit log berhasil dimuat');
    }
}
