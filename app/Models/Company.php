<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Company extends Model
{
    use HasFactory;

    protected $primaryKey = 'company_id';

    protected $fillable = [
        'company_name',
        'company_abn',
        'company_acn',
        'company_primary_location_id',
        'gst_registered',
        'gst_number',
        'gst_registration_start_date',
        'company_registration_date',
        'primary_contact_person',
        'company_type',
        'subscriber_id',
        'user_id',
    ];

    protected $casts = [
        'gst_registered' => 'boolean',
        'gst_registration_start_date' => 'date',
        'company_registration_date' => 'date',
    ];

    // Relationships
    public function locations()
    {
        return $this->hasMany(Location::class);
    }

    /**
     * Get attachments for this company
     */
    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'type_id', 'company_id')
                    ->where('type', 'Company');
    }

    /**
     * Get attachments count for this company
     */
    public function getAttachmentsCountAttribute()
    {
        return $this->attachments()->count();
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'emp_company_id', 'company_id');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'company_id', 'company_id');
    }

    public function primaryContact()
    {
        return $this->belongsTo(Employee::class, 'primary_contact_person', 'emp_id');
    }

    public function subscriber()
    {
        return $this->belongsTo(Company::class, 'subscriber_id', 'company_id');
    }

    public function clients()
    {
        return $this->hasMany(Company::class, 'subscriber_id', 'company_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
