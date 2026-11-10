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
        Schema::table('invoices', function (Blueprint $table) {
            // Add discount fields
            $table->decimal('discount_type', 10, 2)->default(0.00)->after('invoice_amount'); // percentage or fixed amount
            $table->enum('discount_method', ['percentage', 'fixed'])->default('percentage')->after('discount_type');
            $table->decimal('discount_amount', 12, 2)->default(0.00)->after('discount_method');
            
            // Add tax fields (separate from GST)
            $table->decimal('tax_rate', 5, 2)->default(0.00)->after('discount_amount'); // tax rate in percentage
            $table->decimal('tax_amount', 12, 2)->default(0.00)->after('tax_rate');
            
            // Add subtotal field (after discount, before tax)
            $table->decimal('subtotal', 12, 2)->default(0.00)->after('tax_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Remove discount fields
            $table->dropColumn('discount_type');
            $table->dropColumn('discount_method');
            $table->dropColumn('discount_amount');
            
            // Remove tax fields
            $table->dropColumn('tax_rate');
            $table->dropColumn('tax_amount');
            
            // Remove subtotal field
            $table->dropColumn('subtotal');
        });
    }
};
