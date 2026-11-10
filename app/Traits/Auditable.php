<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    /**
     * Boot the trait and register the model events.
     */
    protected static function bootAuditable()
    {
        static::created(function ($model) {
            $model->logAudit('created');
        });

        static::updated(function ($model) {
            $model->logAudit('updated');
        });

        static::deleted(function ($model) {
            $model->logAudit('deleted');
        });
    }

    /**
     * Log the audit entry.
     */
    protected function logAudit($action)
    {
        $oldValues = null;
        $newValues = null;

        if ($action === 'updated') {
            $oldValues = $this->getOriginal();
            $newValues = $this->getAttributes();
        } elseif ($action === 'created') {
            $newValues = $this->getAttributes();
        } elseif ($action === 'deleted') {
            $oldValues = $this->getAttributes();
        }

        AuditLog::create([
            'model_type' => get_class($this),
            'model_id' => $this->getKey(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'performed_by' => Auth::id(),
            'performed_at' => now(),
        ]);
    }

    /**
     * Get audit logs for this model.
     */
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'model');
    }

    /**
     * Get the latest audit log for this model.
     */
    public function latestAuditLog()
    {
        return $this->morphOne(AuditLog::class, 'model')->latest();
    }
}
