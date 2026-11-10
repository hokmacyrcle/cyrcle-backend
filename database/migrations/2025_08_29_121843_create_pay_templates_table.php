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
        Schema::create('emp_pay_templates', function (Blueprint $table) {
            $table->id('pay_template_id');
            
            // Employee relationship
            $table->unsignedBigInteger('employee_id');
            
            // Template Information
            $table->string('template_name', 255)->nullable(); // Optional template name
            $table->text('description')->nullable(); // Template description
            
            // Template Status
            $table->enum('status', ['active', 'inactive', 'draft'])->default('active');
            
            // Timestamps
            $table->timestamps();
            
            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            
            // Foreign key constraint
            $table->foreign('employee_id')->references('emp_id')->on('employees')->onDelete('cascade');
            
            // Indexes
            $table->index('employee_id');
            $table->index('status');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('emp_pay_template_earnings', function (Blueprint $table) {
            $table->id('earnings_id');
            
            // Foreign key to pay templates table
            $table->unsignedBigInteger('pay_template_id');
            
            // Earnings Rate Information
            $table->string('earnings_rate_name', 255); // e.g., "Ordinary Hours", "Director Fees", "Worker Compensation"
            $table->enum('calculation_type', ['hours_rate', 'fixed_amount', 'mixed'])->default('hours_rate');
            
            // Core fields as per requirements
            $table->decimal('estimate_rate', 10, 6)->nullable(); // Estimated rate
            $table->decimal('hours', 10, 4)->nullable(); // e.g., 76.0000
            $table->decimal('rate', 10, 6)->nullable(); // e.g., 30.000000
            $table->decimal('total', 15, 2)->nullable(); // e.g., 2280.00 (calculated or entered)
            
            // Fixed Amount Field (for fixed_amount calculation type)
            $table->decimal('fixed_amount', 15, 2)->nullable(); // e.g., 1000.00, 800.00
            
            // Mixed Fields (for mixed calculation type)
            $table->decimal('mixed_fixed_amount', 15, 2)->nullable();
            $table->decimal('mixed_rate', 10, 6)->nullable();
            $table->decimal('mixed_total', 15, 2)->nullable();
            
            // Display Order
            $table->integer('display_order')->default(0);
            
            // Status
            $table->boolean('is_active')->default(true);
            
            // Timestamps
            $table->timestamps();
            
            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            
            // Foreign key constraint
            $table->foreign('pay_template_id')->references('pay_template_id')->on('emp_pay_templates')->onDelete('cascade');
            
            // Indexes
            $table->index('pay_template_id');
            $table->index('calculation_type');
            $table->index('earnings_rate_name');
            $table->index('display_order');
            $table->index('is_active');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('emp_pay_template_audit_logs', function (Blueprint $table) {
            $table->id('audit_log_id');
            
            // What was changed
            $table->enum('table_name', ['emp_pay_templates', 'emp_pay_template_earnings'])->notNull();
            $table->unsignedBigInteger('record_id')->notNull(); // ID of the changed record
            
            // Change Information
            $table->enum('action', ['created', 'updated', 'deleted'])->notNull();
            $table->json('old_values')->nullable(); // Previous values
            $table->json('new_values')->nullable(); // New values
            $table->text('change_summary')->nullable(); // Human-readable summary
            
            // User who made the change
            $table->unsignedBigInteger('changed_by')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Indexes
            $table->index('table_name');
            $table->index('record_id');
            $table->index('action');
            $table->index('changed_by');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emp_pay_template_audit_logs');
        Schema::dropIfExists('emp_pay_template_earnings');
        Schema::dropIfExists('emp_pay_templates');
    }
};
