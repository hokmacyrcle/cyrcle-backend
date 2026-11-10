<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'quote_id';

    protected $fillable = [
        'company_id',
        'contact_id',
        'payment_term_id',
        'quote_number',
        'quote_date',
        'expiration_date',
        'status',
        'subtotal',
        'discount_percent',
        'discount_amount',
        'tax_amount',
        'total',
        'notes',
        'terms',
        'attachments',
        'reference',
        'amounts_are',
        'invoice_id',
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
    ];

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class, 'payment_term_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class, 'quote_id');
    }

    /**
     * Get attachments for this quote
     */
    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'type_id', 'quote_id')
                    ->where('type', 'Quote');
    }

    /**
     * Get all attachments for this quote (both legacy and new system)
     */
    public function getAllAttachmentsAttribute()
    {
        $newAttachments = $this->attachments()->get();
        $legacyAttachments = [];
        
        // Check if there are legacy attachments stored in the quotes table
        if (!empty($this->attachments) && is_array($this->attachments)) {
            foreach ($this->attachments as $legacyAttachment) {
                if (is_string($legacyAttachment) && !empty($legacyAttachment)) {
                    $legacyAttachments[] = [
                        'id' => 'legacy_' . uniqid(),
                        'type' => 'Quote',
                        'type_id' => $this->quote_id,
                        'image_url' => $legacyAttachment,
                        'original_name' => basename($legacyAttachment),
                        'filename' => basename($legacyAttachment),
                        'size' => 0,
                        'mime_type' => 'application/octet-stream',
                        'created_at' => $this->created_at,
                        'updated_at' => $this->updated_at,
                        'is_legacy' => true
                    ];
                }
            }
        }
        
        // Combine new and legacy attachments
        $allAttachments = $newAttachments->toArray();
        $allAttachments = array_merge($allAttachments, $legacyAttachments);
        
        return collect($allAttachments);
    }

    /**
     * Get attachments count for this quote
     */
    public function getAttachmentsCountAttribute()
    {
        return $this->getAllAttachmentsAttribute()->count();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(QuoteVersion::class, 'quote_id', 'quote_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(QuoteVersion::class, 'quote_id', 'quote_id')
            ->where('is_current_version', true);
    }

    public function latestVersion(): BelongsTo
    {
        return $this->belongsTo(QuoteVersion::class, 'quote_id', 'quote_id')
            ->orderBy('version_number', 'desc');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'Accepted');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'Closed');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'Rejected');
    }

    public function scopeExpired($query)
    {
        return $query->where('expiration_date', '<', now());
    }

    public function scopeNotExpired($query)
    {
        return $query->where('expiration_date', '>', now())->orWhereNull('expiration_date');
    }

    // Accessors
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

    // Methods
    public function calculateTotals(): void
    {
        $this->subtotal = $this->items->sum('amount');
        
        if ($this->discount_percent > 0) {
            $this->discount_amount = ($this->subtotal * $this->discount_percent) / 100;
        }
        
        $this->total = $this->subtotal - $this->discount_amount + $this->tax_amount;
        $this->save();
    }

    public function isExpired(): bool
    {
        return $this->expiration_date && $this->expiration_date->isPast();
    }

    public function canBeConvertedToInvoice(): bool
    {
        return in_array($this->status, ['Accepted', 'Pending']) && !$this->isExpired();
    }
}
