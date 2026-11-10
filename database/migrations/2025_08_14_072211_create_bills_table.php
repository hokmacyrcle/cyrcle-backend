<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            // Keys
            $table->id('bill_id');                        // Primary Key
            $table->unsignedBigInteger('company_id');     // FK -> companies.id
            $table->unsignedBigInteger('contact_id');     // FK -> contacts.id (Received From)

            // Core fields
            $table->date('bill_date');
            $table->decimal('bill_amount', 12, 2);        // excl. GST
            $table->decimal('gst_amount', 12, 2)->default(0);
            $table->decimal('bill_total', 12, 2);         // bill_amount + gst_amount
            $table->date('bill_due_date')->nullable();

            // Status fields
            $table->enum('bill_status', ['Due', 'Past Due'])->default('Due');         // based on due date
            $table->enum('bill_payment_status', ['Paid', 'Unpaid'])->default('Unpaid');

            // Payments summary
            $table->decimal('bill_paid_amount', 12, 2)->default(0); // sum of payments

            // Attachment (path or URL)
            $table->string('bill_attachment')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['company_id', 'contact_id']);
            $table->index(['bill_status', 'bill_payment_status']);
            $table->index('bill_due_date');

            // Foreign keys
            $table->foreign('company_id')->references('company_id')->on('companies')
                  ->cascadeOnUpdate()->restrictOnDelete();

            $table->foreign('contact_id')->references('contact_id')->on('contacts')
                  ->cascadeOnUpdate()->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
