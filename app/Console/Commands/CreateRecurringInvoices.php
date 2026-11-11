<?php

namespace App\Console\Commands;

use App\Services\Sales\RecurringInvoices;
use Illuminate\Console\Command;

class CreateRecurringInvoices extends Command
{
    protected $signature = 'invoices:recurring';
    protected $description = 'Create draft invoices for recurring schedules that are due today';

    public function handle(): int
    {
        $n = (new RecurringInvoices())->run();
        $this->info("Created {$n} draft invoice(s).");
        return self::SUCCESS;
    }
}
