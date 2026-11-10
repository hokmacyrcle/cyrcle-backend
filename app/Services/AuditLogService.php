<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * Log a model creation
     */
    public static function logCreated(Model $model, array $data = []): void
    {
        self::createLog($model, 'created', null, $data);
    }

    /**
     * Log a model update
     */
    public static function logUpdated(Model $model, array $oldValues = [], array $newValues = []): void
    {
        self::createLog($model, 'updated', $oldValues, $newValues);
    }

    /**
     * Log a model deletion
     */
    public static function logDeleted(Model $model, array $oldValues = []): void
    {
        self::createLog($model, 'deleted', $oldValues, null);
    }

    /**
     * Log a model restoration (from soft delete)
     */
    public static function logRestored(Model $model): void
    {
        self::createLog($model, 'restored', null, null);
    }

    /**
     * Log a model force deletion
     */
    public static function logForceDeleted(Model $model, array $oldValues = []): void
    {
        self::createLog($model, 'force_deleted', $oldValues, null);
    }

    /**
     * Log a custom action
     */
    public static function logAction(Model $model, string $action, array $oldValues = [], array $newValues = []): void
    {
        self::createLog($model, $action, $oldValues, $newValues);
    }

    /**
     * Log a custom event without a model
     */
    public static function logEvent(string $event, array $data = [], ?string $modelType = null, ?int $modelId = null): void
    {
        self::createLogEntry([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'action' => $event,
            'old_values' => null,
            'new_values' => $data,
            'performed_by' => Auth::id(),
            'performed_at' => now(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
            'method' => Request::method(),
        ]);
    }

    /**
     * Create the actual audit log entry
     */
    private static function createLog(Model $model, string $action, ?array $oldValues, ?array $newValues): void
    {
        try {
            $data = [
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'action' => $action,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'performed_by' => Auth::id(),
                'performed_at' => now(),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'url' => Request::fullUrl(),
                'method' => Request::method(),
            ];

            self::createLogEntry($data);
        } catch (\Exception $e) {
            // Log the error but don't break the main operation
            \Log::error('Audit logging failed: ' . $e->getMessage(), [
                'model' => get_class($model),
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create the audit log entry in the database
     */
    private static function createLogEntry(array $data): void
    {
        try {
            AuditLog::create($data);
        } catch (\Exception $e) {
            // If audit logging fails, log it but don't break the main operation
            \Log::error('Failed to create audit log entry: ' . $e->getMessage(), $data);
        }
    }

    /**
     * Get audit logs for a specific model
     */
    public static function getModelLogs(string $modelType, ?int $modelId = null, int $limit = 50)
    {
        $query = AuditLog::where('model_type', $modelType);
        
        if ($modelId) {
            $query->where('model_id', $modelId);
        }
        
        return $query->orderBy('performed_at', 'desc')->limit($limit)->get();
    }

    /**
     * Get audit logs for a specific user
     */
    public static function getUserLogs(int $userId, int $limit = 50)
    {
        return AuditLog::where('performed_by', $userId)
            ->orderBy('performed_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get audit logs for a specific action
     */
    public static function getActionLogs(string $action, int $limit = 50)
    {
        return AuditLog::where('action', $action)
            ->orderBy('performed_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get audit logs within a date range
     */
    public static function getDateRangeLogs(string $startDate, string $endDate, int $limit = 100)
    {
        return AuditLog::whereBetween('performed_at', [$startDate, $endDate])
            ->orderBy('performed_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Clean up old audit logs (keep last 12 months by default)
     */
    public static function cleanupOldLogs(int $monthsToKeep = 12): int
    {
        $cutoffDate = now()->subMonths($monthsToKeep);
        
        return AuditLog::where('performed_at', '<', $cutoffDate)->delete();
    }
}
