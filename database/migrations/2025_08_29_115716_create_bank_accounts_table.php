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
        Schema::create('emp_bank_accounts', function (Blueprint $table) {
            $table->id('bank_account_id');
            
            // Foreign key to employees table
            $table->unsignedBigInteger('employee_id');
            
            // Form fields from image
            $table->string('statement_text', 255)->nullable(); // e.g., "Salary", "Pay Cheque"
            $table->string('account_name', 255); // e.g., "O Gray"
            $table->string('bsb_number', 10)->nullable(); // e.g., "234-567"
            $table->string('account_number', 50); // e.g., "123451222"
            $table->decimal('balance', 15, 2)->default(0.00); // Balance amount
            
            // Primary account flag
            $table->boolean('is_primary_account')->default(false); // Primary account for payments
            
            // Status field
            $table->enum('status', ['active', 'inactive'])->default('active');
            
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
            $table->index('is_primary_account');
            $table->index('created_by');
            $table->index('updated_by');
            
            // Unique constraint - one primary account per employee
            $table->unique(['employee_id', 'is_primary_account'], 'unique_primary_account_per_employee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emp_bank_accounts');
    }
};
