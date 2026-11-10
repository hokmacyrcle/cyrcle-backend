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
        Schema::create('quotes', function (Blueprint $table) {
            // Primary Key
            $table->bigIncrements('quote_id');

            // Foreign Keys
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('contact_id')->index(); // FK to contacts
            $table->unsignedBigInteger('payment_term_id')->nullable()->index();

            // Quote basic info
            $table->string('quote_number')->unique();
            $table->date('quote_date');
            $table->date('expiration_date')->nullable();
            
            // Status
            $table->enum('status', ['Pending', 'Accepted', 'Closed', 'Rejected'])->default('Pending');
            
            // Customer info
            $table->string('customer_email')->nullable();
            $table->text('billing_address')->nullable();
            
            // Financial fields
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('discount_percent', 5, 2)->default(0.00);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('tax_amount', 12, 2)->default(0.00);
            $table->decimal('total', 12, 2)->default(0.00);
            
            // Additional fields
            $table->text('message_on_quote')->nullable();
            $table->text('message_on_statement')->nullable();
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            
            // Attachments
            $table->text('attachments')->nullable(); // JSON array of file paths
            
            // Send later option
            $table->boolean('send_later')->default(false);
            $table->timestamp('scheduled_at')->nullable();
            
            // Timestamps & soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['company_id', 'status']);
            $table->index(['quote_date', 'status']);
            $table->index('quote_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
