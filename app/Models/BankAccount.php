<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class BankAccount extends Model
{
    use HasFactory;

    protected $primaryKey = 'bank_account_id';
    protected $table = 'emp_bank_accounts'; // Explicitly set table name

    protected $fillable = [
        'employee_id',
        'statement_text',
        'account_name',
        'bsb_number',
        'account_number',
        'balance',
        'is_primary_account',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_primary_account' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    // Relationships
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'emp_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary_account', true);
    }

    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    // Accessors
    public function getFormattedBalanceAttribute()
    {
        return '$' . number_format($this->balance, 2);
    }

    public function getFormattedBSBAttribute()
    {
        if (empty($this->bsb_number)) {
            return null;
        }

        // Format BSB as XXX-XXX
        $bsb = $this->bsb_number;
        if (strlen($bsb) === 6) {
            return substr($bsb, 0, 3) . '-' . substr($bsb, 3, 3);
        }

        return $bsb;
    }

    public function getFormattedAccountNumberAttribute()
    {
        if (empty($this->account_number)) {
            return null;
        }

        // Mask account number for security (show only last 4 digits)
        $length = strlen($this->account_number);
        if ($length <= 4) {
            return $this->account_number;
        }

        return str_repeat('*', $length - 4) . substr($this->account_number, -4);
    }

    public function getAccountDisplayNameAttribute()
    {
        $display = $this->account_name;
        
        if ($this->statement_text) {
            $display .= ' (' . $this->statement_text . ')';
        }
        
        return $display;
    }

    public function getIsPrimaryAttribute()
    {
        return $this->is_primary_account;
    }

    // Methods
    public function activate()
    {
        $this->update(['status' => self::STATUS_ACTIVE]);
        return $this;
    }

    public function deactivate()
    {
        $this->update(['status' => self::STATUS_INACTIVE]);
        return $this;
    }

    public function isActive()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function setAsPrimary()
    {
        // Remove primary status from other accounts of the same employee
        self::where('employee_id', $this->employee_id)
            ->where('bank_account_id', '!=', $this->bank_account_id)
            ->update(['is_primary_account' => false]);

        // Set this account as primary
        $this->update(['is_primary_account' => true]);
        return $this;
    }

    public function updateBalance($amount)
    {
        $this->update(['balance' => $amount]);
        return $this;
    }

    public function addToBalance($amount)
    {
        $this->update(['balance' => $this->balance + $amount]);
        return $this;
    }

    public function deductFromBalance($amount)
    {
        $this->update(['balance' => max(0, $this->balance - $amount)]);
        return $this;
    }

    public function updateStatementText($statementText)
    {
        $this->update(['statement_text' => $statementText]);
        return $this;
    }

    public function updateAccountDetails($accountName, $bsbNumber = null, $accountNumber = null)
    {
        $this->update([
            'account_name' => $accountName,
            'bsb_number' => $bsbNumber,
            'account_number' => $accountNumber,
        ]);
        return $this;
    }

    // Static methods
    public static function getPrimaryAccount($employeeId)
    {
        return self::where('employee_id', $employeeId)
                   ->where('is_primary_account', true)
                   ->first();
    }

    public static function getActiveAccounts($employeeId)
    {
        return self::where('employee_id', $employeeId)
                   ->where('status', self::STATUS_ACTIVE)
                   ->orderBy('is_primary_account', 'desc')
                   ->orderBy('created_at', 'asc')
                   ->get();
    }

    public static function getAccountsByEmployee($employeeId)
    {
        return self::where('employee_id', $employeeId)
                   ->orderBy('is_primary_account', 'desc')
                   ->orderBy('created_at', 'asc')
                   ->get();
    }

    public static function getAccountStatistics()
    {
        return [
            'total_accounts' => self::count(),
            'active_accounts' => self::active()->count(),
            'inactive_accounts' => self::inactive()->count(),
            'primary_accounts' => self::primary()->count(),
            'total_balance' => self::sum('balance'),
            'average_balance' => self::avg('balance'),
        ];
    }

    public static function validateBSB($bsb)
    {
        // Remove non-numeric characters
        $bsb = preg_replace('/[^0-9]/', '', $bsb);
        
        // Check length
        if (strlen($bsb) !== 6) {
            return false;
        }
        
        // Basic BSB validation (first 2 digits should be 01-99)
        $firstTwo = intval(substr($bsb, 0, 2));
        if ($firstTwo < 1 || $firstTwo > 99) {
            return false;
        }
        
        return true;
    }

    public static function validateAccountNumber($accountNumber)
    {
        // Remove non-alphanumeric characters
        $accountNumber = preg_replace('/[^a-zA-Z0-9]/', '', $accountNumber);
        
        // Check length (typically 4-20 characters)
        if (strlen($accountNumber) < 4 || strlen($accountNumber) > 20) {
            return false;
        }
        
        return true;
    }
}
