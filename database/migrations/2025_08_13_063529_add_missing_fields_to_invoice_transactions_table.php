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
        Schema::table('invoice_transactions', function (Blueprint $table) {
            // Add payment_date field
            $table->date('payment_date')->nullable()->after('payment_amount');
            
            // Add payment_method field
            $table->string('payment_method', 100)->nullable()->after('payment_date');
            
            // Add reference_number field
            $table->string('reference_number', 100)->nullable()->after('payment_method');
            
            // Add notes field
            $table->text('notes')->nullable()->after('reference_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_transactions', function (Blueprint $table) {
            // Remove the added fields
            $table->dropColumn(['payment_date', 'payment_method', 'reference_number', 'notes']);
        });
    }
};
