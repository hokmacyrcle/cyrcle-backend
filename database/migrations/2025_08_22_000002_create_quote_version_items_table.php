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
        Schema::create('quote_version_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quote_version_id');
            $table->string('product_service');
            $table->text('description')->nullable();
            $table->integer('quantity');
            $table->decimal('rate', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->decimal('gst', 12, 2)->default(0.00);
            $table->decimal('gst_rate', 5, 2)->default(10.00); // Default 10% GST
            $table->decimal('total', 12, 2); // amount + gst
            $table->text('notes')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('quote_version_id');
            $table->index('product_service'); // Index only the product_service field

            // Foreign key constraints
            //$table->foreign('quote_version_id')->references('id')->on('quote_versions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_version_items');
    }
};
