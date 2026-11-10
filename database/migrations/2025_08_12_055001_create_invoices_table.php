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
        Schema::create('invoices', function (Blueprint $table) {

            // Primary Key
            $table->bigIncrements('invoice_id');

            // Foreign Key: Company
            $table->unsignedBigInteger('company_id')->index();

            // Invoice basic info
            $table->date('invoice_date');
            $table->unsignedBigInteger('sent_to')->index(); // FK to contacts

            // Financial fields
            $table->decimal('invoice_amount', 12, 2)->default(0.00); // excluding GST
            $table->decimal('gst_amount', 12, 2)->default(0.00);
            $table->decimal('invoice_total', 12, 2)->default(0.00); // including GST

            // Due & Status fields
            $table->date('invoice_due_date')->nullable();
            $table->enum('invoice_status', ['Due', 'Past Due'])->default('Due');
            $table->enum('invoice_payment_status', ['Paid', 'Unpaid', 'Partially Paid'])->default('Unpaid');

            // Payment info
            $table->decimal('invoice_paid_amount', 12, 2)->default(0.00);

            // Attachments
            $table->text('invoice_attachment')->nullable(); // link or path

            // Timestamps & soft deletes
            $table->timestamps();
            $table->softDeletes();

            // // Foreign key constraints
            // $table->foreign('company_id')
            //     ->references('id')->on('companies')
            //     ->cascadeOnUpdate()->restrictOnDelete();

            // $table->foreign('sent_to')
            //     ->references('id')->on('contacts')
            //     ->cascadeOnUpdate()->restrictOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
