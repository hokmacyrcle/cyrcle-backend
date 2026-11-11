<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecurringInvoice extends Model
{
    protected $table = 'recurring_invoices';
    protected $primaryKey = 'recurring_invoice_id';

    protected $fillable = ['company_id', 'source_invoice_id', 'frequency', 'start_date', 'runs', 'next_date', 'end_date', 'active', 'last_run_at'];

    protected $casts = [
        'start_date' => 'date',
        'next_date' => 'date',
        'end_date' => 'date',
        'active' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    public const FREQUENCIES = ['weekly' => 'Every week', 'fortnightly' => 'Every 2 weeks', 'monthly' => 'Every month', 'quarterly' => 'Every 3 months', 'yearly' => 'Every year'];

    public function source()
    {
        return $this->belongsTo(Invoice::class, 'source_invoice_id', 'invoice_id');
    }
}
