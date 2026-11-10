<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteVersion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'quote_id',
        'version_number',
        'created_by',
        'company_id',
        'contact_id',
        'payment_term_id',
        'quote_number',
        'quote_date',
        'expiration_date',
        'status',
        'customer_email',
        'billing_address',
        'subtotal',
        'discount_percent',
        'discount_amount',
        'tax_amount',
        'total',
        'message_on_quote',
        'message_on_statement',
        'notes',
        'terms',
        'attachments',
        'send_later',
        'scheduled_at',
        'change_summary',
        'change_reason',
        'is_current_version',
    ];

    protected $casts = [
        'quote_date' => 'date',
        'expiration_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'attachments' => 'array',
        'send_later' => 'boolean',
        'scheduled_at' => 'datetime',
        'is_current_version' => 'boolean',
    ];

    // Relationships
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'quote_id', 'quote_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id', 'contact_id');
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class, 'payment_term_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteVersionItem::class, 'quote_version_id');
    }

    // Scopes
    public function scopeCurrentVersion($query)
    {
        return $query->where('is_current_version', true);
    }

    public function scopeByQuote($query, $quoteId)
    {
        return $query->where('quote_id', $quoteId);
    }

    public function scopeOrderedByVersion($query)
    {
        return $query->orderBy('version_number', 'desc');
    }

    // Methods
    public function getFormattedVersionNumberAttribute(): string
    {
        return 'v' . $this->version_number;
    }

    public function getFormattedTotalAttribute(): string
    {
        return 'A$' . number_format($this->total, 2);
    }

    public function getFormattedSubtotalAttribute(): string
    {
        return 'A$' . number_format($this->subtotal, 2);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'Pending' => 'warning',
            'Accepted' => 'success',
            'Closed' => 'info',
            'Rejected' => 'danger',
            default => 'secondary'
        };
    }

    public function getChangeSummaryAttribute($value): string
    {
        return $value ?: 'No changes documented';
    }

    public function getChangeReasonAttribute($value): string
    {
        return $value ?: 'No reason provided';
    }

    /**
     * Check if this version is the latest
     */
    public function isLatest(): bool
    {
        return $this->is_current_version;
    }

    /**
     * Get the previous version
     */
    public function getPreviousVersion(): ?self
    {
        return static::where('quote_id', $this->quote_id)
            ->where('id', '<', $this->id)
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Get the next version
     */
    public function getNextVersion(): ?self
    {
        return static::where('quote_id', $this->quote_id)
            ->where('id', '>', $this->id)
            ->orderBy('id', 'asc')
            ->first();
    }

    /**
     * Get all versions of this quote
     */
    public function getAllVersions()
    {
        return static::where('quote_id', $this->quote_id)
            ->orderBy('version_number', 'desc')
            ->get();
    }

    /**
     * Compare this version with another version
     */
    public function compareWith(QuoteVersion $otherVersion): array
    {
        $changes = [];

        // Compare basic fields
        $basicFields = [
            'subtotal', 'discount_percent', 'discount_amount', 
            'tax_amount', 'total', 'notes', 'terms'
        ];

        foreach ($basicFields as $field) {
            if ($this->$field != $otherVersion->$field) {
                $changes[$field] = [
                    'from' => $otherVersion->$field,
                    'to' => $this->$field
                ];
            }
        }

        // Compare items
        $thisItems = $this->items->keyBy('id');
        $otherItems = $otherVersion->items->keyBy('id');

        $changes['items'] = [
            'added' => $thisItems->diffKeys($otherItems)->values(),
            'removed' => $otherItems->diffKeys($thisItems)->values(),
            'modified' => []
        ];

        // Find modified items
        foreach ($thisItems as $itemId => $thisItem) {
            if ($otherItems->has($itemId)) {
                $otherItem = $otherItems->get($itemId);
                if ($thisItem->quantity != $otherItem->quantity ||
                    $thisItem->rate != $otherItem->rate ||
                    $thisItem->amount != $otherItem->amount) {
                    $changes['items']['modified'][] = [
                        'item_id' => $itemId,
                        'from' => $otherItem->toArray(),
                        'to' => $thisItem->toArray()
                    ];
                }
            }
        }

        return $changes;
    }
}
