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
        Schema::create('status_history', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedBigInteger('created_by');
            $table->enum('type', ['Quotes', 'Invoice']);
            $table->string('status');
            $table->unsignedBigInteger('record_id'); // This will store either quote_id or invoice_id
            $table->text('notes')->nullable(); // Optional notes about the status change
            $table->timestamps();

            // Indexes for better performance
            $table->index(['type', 'record_id']);
            $table->index('created_by');
            $table->index('date');

            // Foreign key constraints
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_history');
    }
};
