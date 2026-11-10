<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PayTemplateAuditLog extends Model
{
    use HasFactory;

    protected $primaryKey = 'audit_log_id';

    protected $fillable = [
        'table_name',
        'record_id',
        'action',
        'old_values',
        'new_values',
        'change_summary',
        'changed_by',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Table name constants
    const TABLE_PAY_TEMPLATES = 'emp_pay_templates';
    const TABLE_PAY_TEMPLATE_EARNINGS = 'emp_pay_template_earnings';

    // Action constants
    const ACTION_CREATED = 'created';
    const ACTION_UPDATED = 'updated';
    const ACTION_DELETED = 'deleted';

    // Relationships
    public function changedByUser()
    {
        return $this->belongsTo(User::class, 'changed_by', 'id');
    }

    // Scopes
    public function scopeByTable($query, $tableName)
    {
        return $query->where('table_name', $tableName);
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByRecord($query, $recordId)
    {
        return $query->where('record_id', $recordId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('changed_by', $userId);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays($days));
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', Carbon::today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek()
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', Carbon::now()->month)
                    ->whereYear('created_at', Carbon::now()->year);
    }

    // Accessors
    public function getTableNameLabelAttribute()
    {
        $labels = [
            self::TABLE_PAY_TEMPLATES => 'Pay Template',
            self::TABLE_PAY_TEMPLATE_EARNINGS => 'Pay Template Earning',
        ];
        
        return $labels[$this->table_name] ?? $this->table_name;
    }

    public function getActionLabelAttribute()
    {
        return ucfirst($this->action);
    }

    public function getFormattedOldValuesAttribute()
    {
        if (!$this->old_values) {
            return 'No previous values';
        }
        
        return $this->formatValues($this->old_values);
    }

    public function getFormattedNewValuesAttribute()
    {
        if (!$this->new_values) {
            return 'No new values';
        }
        
        return $this->formatValues($this->new_values);
    }

    public function getChangeSummaryAttribute($value)
    {
        if ($value) {
            return $value;
        }
        
        // Auto-generate summary if not provided
        return $this->generateChangeSummary();
    }

    public function getIsCreatedAttribute()
    {
        return $this->action === self::ACTION_CREATED;
    }

    public function getIsUpdatedAttribute()
    {
        return $this->action === self::ACTION_UPDATED;
    }

    public function getIsDeletedAttribute()
    {
        return $this->action === self::ACTION_DELETED;
    }

    // Methods
    public function formatValues($values)
    {
        if (!is_array($values)) {
            return $values;
        }
        
        $formatted = [];
        foreach ($values as $key => $value) {
            if (is_numeric($value)) {
                if (strpos($key, 'rate') !== false || strpos($key, 'hours') !== false) {
                    $formatted[$key] = number_format($value, 6);
                } elseif (strpos($key, 'amount') !== false || strpos($key, 'total') !== false) {
                    $formatted[$key] = '$' . number_format($value, 2);
                } else {
                    $formatted[$key] = number_format($value);
                }
            } else {
                $formatted[$key] = $value;
            }
        }
        
        return $formatted;
    }

    public function generateChangeSummary()
    {
        switch ($this->action) {
            case self::ACTION_CREATED:
                return "New {$this->table_name_label} created";
                
            case self::ACTION_UPDATED:
                if ($this->old_values && $this->new_values) {
                    $changes = [];
                    foreach ($this->new_values as $key => $newValue) {
                        if (isset($this->old_values[$key]) && $this->old_values[$key] !== $newValue) {
                            $changes[] = "{$key}: {$this->old_values[$key]} → {$newValue}";
                        }
                    }
                    return count($changes) > 0 ? implode(', ', $changes) : 'No significant changes';
                }
                return "{$this->table_name_label} updated";
                
            case self::ACTION_DELETED:
                return "{$this->table_name_label} deleted";
                
            default:
                return "Action performed on {$this->table_name_label}";
        }
    }

    // Static methods
    public static function logCreation($tableName, $recordId, $newValues, $userId = null)
    {
        return self::create([
            'table_name' => $tableName,
            'record_id' => $recordId,
            'action' => self::ACTION_CREATED,
            'new_values' => $newValues,
            'changed_by' => $userId ?? auth()->id(),
        ]);
    }

    public static function logUpdate($tableName, $recordId, $oldValues, $newValues, $userId = null)
    {
        return self::create([
            'table_name' => $tableName,
            'record_id' => $recordId,
            'action' => self::ACTION_UPDATED,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_by' => $userId ?? auth()->id(),
        ]);
    }

    public static function logDeletion($tableName, $recordId, $oldValues, $userId = null)
    {
        return self::create([
            'table_name' => $tableName,
            'record_id' => $recordId,
            'action' => self::ACTION_DELETED,
            'old_values' => $oldValues,
            'changed_by' => $userId ?? auth()->id(),
        ]);
    }

    public static function getAuditTrail($tableName, $recordId)
    {
        return self::byTable($tableName)
                   ->byRecord($recordId)
                   ->orderBy('created_at', 'desc')
                   ->get();
    }

    public static function getUserActivity($userId, $days = 30)
    {
        return self::byUser($userId)
                   ->recent($days)
                   ->orderBy('created_at', 'desc')
                   ->get();
    }

    public static function getSystemActivity($days = 30)
    {
        return self::recent($days)
                   ->with('changedByUser')
                   ->orderBy('created_at', 'desc')
                   ->get();
    }

    public static function getActivityStatistics($days = 30)
    {
        $stats = self::recent($days);
        
        return [
            'total_actions' => $stats->count(),
            'creations' => $stats->byAction(self::ACTION_CREATED)->count(),
            'updates' => $stats->byAction(self::ACTION_UPDATED)->count(),
            'deletions' => $stats->byAction(self::ACTION_DELETED)->count(),
            'pay_template_changes' => $stats->byTable(self::TABLE_PAY_TEMPLATES)->count(),
            'earnings_changes' => $stats->byTable(self::TABLE_PAY_TEMPLATE_EARNINGS)->count(),
            'today_actions' => self::today()->count(),
            'this_week_actions' => self::thisWeek()->count(),
            'this_month_actions' => self::thisMonth()->count(),
        ];
    }
}
