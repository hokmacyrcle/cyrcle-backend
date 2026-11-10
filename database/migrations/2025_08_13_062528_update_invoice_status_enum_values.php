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
        // First, we need to modify the column type to allow new enum values
        DB::statement("ALTER TABLE invoices MODIFY COLUMN invoice_status ENUM('Draft', 'Sent', 'Due', 'Past Due', 'Paid', 'Cancelled') DEFAULT 'Draft'");
        
        // Also update the payment status enum to be more comprehensive
        DB::statement("ALTER TABLE invoices MODIFY COLUMN invoice_payment_status ENUM('Unpaid', 'Partially Paid', 'Paid', 'Overdue') DEFAULT 'Unpaid'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE invoices MODIFY COLUMN invoice_status ENUM('Due', 'Past Due') DEFAULT 'Due'");
        DB::statement("ALTER TABLE invoices MODIFY COLUMN invoice_payment_status ENUM('Paid', 'Unpaid', 'Partially Paid') DEFAULT 'Unpaid'");
    }
};
