<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $primaryKey = 'emp_id';

    protected $appends = [
        'employee_id',
    ];

    protected $fillable = [
        'emp_title',
        'emp_first_name',
        'emp_middle_name',
        'emp_last_name',
        'emp_date_of_birth',
        'emp_job_title',
        'emp_gender',
        'emp_email_address',
        'emp_phone_number',
        'emp_mobile_number',
        'emp_company_id',
        'emp_status',
        'emp_designation_id',
        'emp_start_date',
        'emp_resignation_date',
        'emp_last_working_date',
        'emp_termination_date',
        'emp_contract_end_date',
        'emp_tfn',
        'emp_work_authorization',
        'emp_visa_subclass',
        'emp_visa_expiry_date',
        'emp_employment_type',
        'emp_address_search',
        'emp_xero_me_invitation',
        'profile_pic',
        'emp_address_line_1',
        'emp_address_line_2',
        'emp_address_line_3',
        'emp_city',
        'emp_state',
        'emp_post_code',
        'emp_country',
        'emp_emergency_contact_name',
        'emp_emergency_contact_relationship',
        'emp_emergency_contact_phone',
        'emp_emergency_contact_email',
    ];

    protected $casts = [
        'emp_date_of_birth' => 'date',
        'emp_start_date' => 'date',
        'emp_resignation_date' => 'date',
        'emp_last_working_date' => 'date',
        'emp_termination_date' => 'date',
        'emp_contract_end_date' => 'date',
        'emp_visa_expiry_date' => 'date',
        'emp_xero_me_invitation' => 'boolean',
    ];

    /**
     * Expose employee_id alongside emp_id for API consumers.
     */
    public function getEmployeeIdAttribute(): int
    {
        return (int) ($this->attributes['emp_id'] ?? $this->emp_id);
    }

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class, 'emp_company_id', 'company_id');
    }

    public function designations()
    {
        return $this->hasMany(EmployeeDesignation::class, 'employee_id', 'emp_id');
    }

    public function currentDesignation()
    {
        return $this->hasOne(EmployeeDesignation::class, 'employee_id', 'emp_id')
                    ->where('record_status', 'Active')
                    ->whereNull('designation_end_date');
    }

    /**
     * Get attachments for this employee
     */
    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'type_id', 'emp_id')
                    ->where('type', 'Employee');
    }

    /**
     * Get attachments count for this employee
     */
    public function getAttachmentsCountAttribute()
    {
        return $this->attachments()->count();
    }

    /**
     * Get full name with title
     */
    public function getFullNameWithTitleAttribute()
    {
        $name = $this->emp_first_name;
        if ($this->emp_middle_name) {
            $name .= ' ' . $this->emp_middle_name;
        }
        $name .= ' ' . $this->emp_last_name;
        
        if ($this->emp_title) {
            $name = $this->emp_title . ' ' . $name;
        }
        
        return $name;
    }

    /**
     * Get full name without title
     */
    public function getFullNameAttribute()
    {
        $name = $this->emp_first_name;
        if ($this->emp_middle_name) {
            $name .= ' ' . $this->emp_middle_name;
        }
        $name .= ' ' . $this->emp_last_name;
        
        return $name;
    }

    /**
     * Get age from date of birth
     */
    public function getAgeAttribute()
    {
        if ($this->emp_date_of_birth) {
            return $this->emp_date_of_birth->age;
        }
        return null;
    }

    /**
     * Check if employee is invited to Xero Me
     */
    public function isInvitedToXeroMe()
    {
        return $this->emp_xero_me_invitation;
    }



            // Employment Information relationship
        public function employmentInformation()
        {
            return $this->hasOne(EmploymentInformation::class, 'employee_id', 'emp_id');
        }

        // Tax Details relationship
        public function taxDetails()
        {
            return $this->hasOne(TaxDetail::class, 'employee_id', 'emp_id');
        }

        // Bank Account relationship
        public function bankAccounts()
        {
            return $this->hasMany(BankAccount::class, 'employee_id', 'emp_id');
        }

        public function primaryBankAccount()
        {
            return $this->hasOne(BankAccount::class, 'employee_id', 'emp_id')
                        ->where('is_primary_account', true);
        }

        // Deductions relationship
        public function deductions()
        {
            return $this->hasMany(Deduction::class, 'employee_id', 'emp_id');
        }

        public function activeDeductions()
        {
            return $this->hasMany(Deduction::class, 'employee_id', 'emp_id')
                        ->where('status', 'active');
        }

        public function preTaxDeductions()
        {
            return $this->hasMany(Deduction::class, 'employee_id', 'emp_id')
                        ->where('deduction_category', 'pre_tax')
                        ->where('status', 'active');
        }

        public function postTaxDeductions()
        {
            return $this->hasMany(Deduction::class, 'employee_id', 'emp_id')
                        ->where('deduction_category', 'post_tax')
                        ->where('status', 'active');
        }


}
