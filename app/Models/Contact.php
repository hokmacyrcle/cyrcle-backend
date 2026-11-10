<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected $primaryKey = 'contact_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'contact_type',
        'contact_first_name',
        'contact_middle_name',
        'contact_last_name',
        'contact_company_name',
        'contact_phone_number',
        'contact_mobile_number',
        'contact_email_address',
        'contact_address_line_1',
        'contact_address_line_2',
        'contact_address_line_3',
        'contact_city',
        'contact_state',
        'contact_post_code',
        'contact_country',
        'contact_category',
        'contact_status',
        'subscriber_client',
        'subscriber_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'subscriber_client' => 'boolean',
        'contact_status' => 'string',
        'contact_type' => 'string',
        'contact_category' => 'string',
    ];

    /**
     * Scope a query to only include active contacts.
     */
    public function scopeActive($query)
    {
        return $query->where('contact_status', 'Active');
    }

    /**
     * Scope a query to only include inactive contacts.
     */
    public function scopeInactive($query)
    {
        return $query->where('contact_status', 'Inactive');
    }

    /**
     * Scope a query to only include subscriber clients.
     */
    public function scopeSubscriberClients($query)
    {
        return $query->where('subscriber_client', true);
    }

    /**
     * Get the full name for individual contacts.
     */
    public function getFullNameAttribute()
    {
        if (in_array($this->contact_type, ['Individual', 'Sole Trader'])) {
            return trim(implode(' ', array_filter([
                $this->contact_first_name,
                $this->contact_middle_name,
                $this->contact_last_name
            ])));
        }
        return $this->contact_company_name;
    }

    /**
     * Get the display name based on contact type.
     */
    public function getDisplayNameAttribute()
    {
        if ($this->contact_type === 'Company') {
            return $this->contact_company_name;
        }
        return $this->full_name;
    }

    /**
     * Get the audit logs for this contact.
     */
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'model');
    }

    /**
     * Get the company that owns the contact.
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }
}
