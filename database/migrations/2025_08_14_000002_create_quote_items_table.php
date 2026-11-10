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
        Schema::create('quote_items', function (Blueprint $table) {
            // Primary Key
            $table->bigIncrements('item_id');

            // Foreign Key to quotes
            $table->unsignedBigInteger('quote_id')->index();

            // Item details
            $table->string('product_service')->nullable();
            $table->text('description')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('rate', 12, 2)->default(0.00);
            $table->decimal('amount', 12, 2)->default(0.00);  // quantity × rate
            $table->decimal('gst', 12, 2)->default(0.00);     // GST amount
            $table->string('class')->nullable();               // Item classification
            
            // Timestamps
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('quote_id')
                ->references('quote_id')->on('quotes')
                ->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_items');
    }
};
