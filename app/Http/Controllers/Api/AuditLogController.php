<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuditLogController extends Controller
{
    /**
     * Get all audit logs with filtering and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'model_type' => 'nullable|string',
            'model_id' => 'nullable|integer',
            'action' => 'nullable|string',
            'performed_by' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = AuditLog::with(['performer']);

        // Filter by model type
        if ($request->filled('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        // Filter by model ID
        if ($request->filled('model_id')) {
            $query->where('model_id', $request->model_id);
        }

        // Filter by action
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Filter by user who performed the action
        if ($request->filled('performed_by')) {
            $query->where('performed_by', $request->performed_by);
        }

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->where('performed_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('performed_at', '<=', $request->end_date . ' 23:59:59');
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('model_type', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('url', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 20);
        $auditLogs = $query->orderBy('performed_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Audit logs retrieved successfully',
            'data' => $auditLogs
        ]);
    }

    /**
     * Get audit logs for a specific model
     */
    public function getModelLogs(Request $request, string $modelType, ?int $modelId = null): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $limit = $request->get('limit', 50);
        $logs = AuditLogService::getModelLogs($modelType, $modelId, $limit);

        return response()->json([
            'success' => true,
            'message' => 'Model audit logs retrieved successfully',
            'data' => $logs
        ]);
    }

    /**
     * Get audit logs for a specific user
     */
    public function getUserLogs(Request $request, int $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $limit = $request->get('limit', 50);
        $logs = AuditLogService::getUserLogs($userId, $limit);

        return response()->json([
            'success' => true,
            'message' => 'User audit logs retrieved successfully',
            'data' => $logs
        ]);
    }

    /**
     * Get audit logs for a specific action
     */
    public function getActionLogs(Request $request, string $action): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $limit = $request->get('limit', 50);
        $logs = AuditLogService::getActionLogs($action, $limit);

        return response()->json([
            'success' => true,
            'message' => 'Action audit logs retrieved successfully',
            'data' => $logs
        ]);
    }

    /**
     * Get audit logs within a date range
     */
    public function getDateRangeLogs(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $limit = $request->get('limit', 100);
        $logs = AuditLogService::getDateRangeLogs(
            $request->start_date,
            $request->end_date,
            $limit
        );

        return response()->json([
            'success' => true,
            'message' => 'Date range audit logs retrieved successfully',
            'data' => $logs
        ]);
    }

    /**
     * Get audit log statistics
     */
    public function getStatistics(): JsonResponse
    {
        $stats = [
            'total_logs' => AuditLog::count(),
            'logs_today' => AuditLog::whereDate('performed_at', today())->count(),
            'logs_this_week' => AuditLog::whereBetween('performed_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count(),
            'logs_this_month' => AuditLog::whereMonth('performed_at', now()->month)->count(),
            'actions_breakdown' => AuditLog::selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->orderBy('count', 'desc')
                ->get(),
            'top_models' => AuditLog::selectRaw('model_type, COUNT(*) as count')
                ->groupBy('model_type')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            'top_users' => AuditLog::selectRaw('performed_by, COUNT(*) as count')
                ->with('performer:id,name')
                ->groupBy('performed_by')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Audit log statistics retrieved successfully',
            'data' => $stats
        ]);
    }

    /**
     * Clean up old audit logs
     */
    public function cleanupOldLogs(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'months_to_keep' => 'nullable|integer|min:1|max:60',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $monthsToKeep = $request->get('months_to_keep', 12);
        $deletedCount = AuditLogService::cleanupOldLogs($monthsToKeep);

        return response()->json([
            'success' => true,
            'message' => "Cleaned up {$deletedCount} old audit logs",
            'data' => [
                'deleted_count' => $deletedCount,
                'months_kept' => $monthsToKeep
            ]
        ]);
    }
}
