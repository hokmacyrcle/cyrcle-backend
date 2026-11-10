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
         Schema::create('invoice_transactions', function (Blueprint $table) {
            // Primary Key
            $table->bigIncrements('transaction_id');

            // Foreign Key to invoices
            $table->unsignedBigInteger('invoice_id')->index();

            // Foreign Key to payments (from payment master)
            $table->unsignedBigInteger('payment_id')->index();

            // Payment Amount
            $table->decimal('payment_amount', 12, 2)->default(0.00);

            // Timestamps
            $table->timestamps();

            // Foreign Key constraints
            // $table->foreign('invoice_id')
            //     ->references('invoice_id')->on('invoices')
            //     ->cascadeOnUpdate()->cascadeOnDelete();

            // $table->foreign('payment_id')
            //     ->references('id')->on('payments')
            //     ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_transactions');
    }
};
