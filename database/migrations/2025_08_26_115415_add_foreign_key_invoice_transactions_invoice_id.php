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
            // Add foreign key constraint for invoice_id
            $table->foreign('invoice_id')
                ->references('invoice_id')
                ->on('invoices')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_transactions', function (Blueprint $table) {
            // Drop foreign key constraint for invoice_id
            $table->dropForeign(['invoice_id']);
        });
    }
};
