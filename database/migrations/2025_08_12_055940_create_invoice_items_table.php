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
        Schema::create('invoice_items', function (Blueprint $table) {
            
            // Primary Key
            $table->bigIncrements('item_id');

            // Foreign Key to invoices
            $table->unsignedBigInteger('invoice_id')->index();

            // Item details
            $table->string('item_description');
            $table->integer('item_quantity')->default(1);
            $table->decimal('item_rate', 12, 2)->default(0.00);
            $table->decimal('item_cost', 12, 2)->default(0.00);  // quantity × rate
            $table->decimal('item_gst', 12, 2)->default(0.00);   // cost × 10%
            $table->decimal('item_total', 12, 2)->default(0.00); // cost + gst

            // Timestamps
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('invoice_id')
                ->references('invoice_id')->on('invoices')
                ->cascadeOnUpdate()->cascadeOnDelete();
        });

        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
