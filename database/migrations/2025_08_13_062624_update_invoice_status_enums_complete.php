<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update invoice_status enum to include all controller-allowed values
        DB::statement("ALTER TABLE invoices MODIFY COLUMN invoice_status ENUM('Due', 'Past Due', 'Draft', 'Sent', 'Viewed', 'Approved', 'Rejected', 'Cancelled') DEFAULT 'Draft'");
        
        // Update invoice_payment_status enum to include all controller-allowed values
        DB::statement("ALTER TABLE invoices MODIFY COLUMN invoice_payment_status ENUM('Unpaid', 'Partially Paid', 'Paid', 'Overdue') DEFAULT 'Unpaid'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to previous enum values
        DB::statement("ALTER TABLE invoices MODIFY COLUMN invoice_status ENUM('Draft', 'Sent', 'Due', 'Past Due', 'Paid', 'Cancelled') DEFAULT 'Draft'");
        DB::statement("ALTER TABLE invoices MODIFY COLUMN invoice_payment_status ENUM('Unpaid', 'Partially Paid', 'Paid', 'Overdue') DEFAULT 'Unpaid'");
    }
};
