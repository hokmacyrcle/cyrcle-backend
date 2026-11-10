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
        Schema::create('quote_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quote_id');
            $table->string('version_number'); // e.g., "1.0", "2.1", "3.0"
            $table->unsignedBigInteger('created_by'); // User who created this version
            
            // Quote data snapshot
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('payment_term_id')->nullable();
            $table->string('quote_number');
            $table->date('quote_date');
            $table->date('expiration_date')->nullable();
            $table->enum('status', ['Pending', 'Accepted', 'Closed', 'Rejected'])->default('Pending');
            $table->string('customer_email')->nullable();
            $table->text('billing_address')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('discount_percent', 5, 2)->default(0.00);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('tax_amount', 12, 2)->default(0.00);
            $table->decimal('total', 12, 2)->default(0.00);
            $table->text('message_on_quote')->nullable();
            $table->text('message_on_statement')->nullable();
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->text('attachments')->nullable(); // JSON array of file paths
            $table->boolean('send_later')->default(false);
            $table->timestamp('scheduled_at')->nullable();
            
            // Version metadata
            $table->text('change_summary')->nullable(); // What changed in this version
            $table->text('change_reason')->nullable(); // Why the change was made
            $table->boolean('is_current_version')->default(false); // Is this the current active version
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['quote_id', 'version_number']);
            $table->index(['quote_id', 'is_current_version']);
            $table->index('created_by');
            $table->index('created_at');

            // Foreign key constraints
            $table->foreign('quote_id')->references('quote_id')->on('quotes')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('company_id')->on('companies')->onDelete('cascade');
            $table->foreign('contact_id')->references('contact_id')->on('contacts')->onDelete('cascade');
            $table->foreign('payment_term_id')->references('id')->on('payment_terms')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_versions');
    }
};
