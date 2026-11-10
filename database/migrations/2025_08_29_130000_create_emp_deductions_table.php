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
        Schema::create('emp_deductions', function (Blueprint $table) {
            $table->id('deduction_id');
            
            // Foreign key to employees table
            $table->unsignedBigInteger('employee_id');
            
            // Core deduction fields from the image
            $table->string('deduction_type', 255)->notNull(); // e.g., "Other Pre-Tax Deductions"
            $table->decimal('amount', 15, 2)->notNull(); // Deduction amount
            
            // Additional deduction details
            $table->enum('deduction_category', ['pre_tax', 'post_tax', 'garnishment', 'other'])->default('pre_tax');
            $table->enum('calculation_type', ['fixed_amount', 'percentage', 'per_pay_period'])->default('fixed_amount');
            $table->decimal('percentage_rate', 5, 2)->nullable(); // For percentage-based deductions
            $table->enum('frequency', ['per_pay_period', 'monthly', 'quarterly', 'annually'])->default('per_pay_period');
            
            // Status and control fields
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->boolean('is_required')->default(false); // Required vs optional deduction
            $table->date('effective_date')->nullable(); // When deduction becomes effective
            $table->date('end_date')->nullable(); // When deduction ends (if applicable)
            
            // Standard audit fields
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            
            // Foreign key constraint
            $table->foreign('employee_id')->references('emp_id')->on('employees')->onDelete('cascade');
            
            // Indexes
            $table->index('employee_id');
            $table->index('deduction_type');
            $table->index('deduction_category');
            $table->index('status');
            $table->index('effective_date');
            $table->index('created_by');
            $table->index('updated_by');
            
            // Unique constraint - one deduction type per employee (can be modified based on business rules)
            $table->unique(['employee_id', 'deduction_type'], 'unique_employee_deduction_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emp_deductions');
    }
};
