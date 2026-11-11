<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceReminder extends Model
{
    protected $table = 'invoice_reminders';
    protected $primaryKey = 'reminder_id';

    protected $fillable = ['company_id', 'invoice_id', 'created_by', 'channel', 'tone', 'subject', 'body', 'days_overdue'];
}
