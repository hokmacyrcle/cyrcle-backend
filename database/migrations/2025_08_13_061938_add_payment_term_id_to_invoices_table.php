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
            // Add payment_term_id foreign key
            $table->unsignedBigInteger('payment_term_id')->nullable()->after('subtotal');
            $table->foreign('payment_term_id')->references('id')->on('payment_terms')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Remove foreign key constraint first
            $table->dropForeign(['payment_term_id']);
            // Remove payment_term_id column
            $table->dropColumn('payment_term_id');
        });
    }
};
