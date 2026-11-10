<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusHistory extends Model
{
    use HasFactory;

    protected $table = 'status_history';

    protected $fillable = [
        'date',
        'created_by',
        'type',
        'status',
        'record_id',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'record_id', 'quote_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'record_id', 'invoice_id');
    }

    // Scopes
    public function scopeQuotes($query)
    {
        return $query->where('type', 'Quotes');
    }

    public function scopeInvoices($query)
    {
        return $query->where('type', 'Invoice');
    }

    public function scopeByRecord($query, $type, $recordId)
    {
        return $query->where('type', $type)->where('record_id', $recordId);
    }

    // Methods
    public function getFormattedDateAttribute(): string
    {
        return $this->date->format('d/m/Y');
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'Pending' => 'warning',
            'Accepted', 'Approved' => 'success',
            'Closed', 'Completed' => 'info',
            'Rejected', 'Cancelled' => 'danger',
            'Draft' => 'secondary',
            default => 'primary'
        };
    }
}
