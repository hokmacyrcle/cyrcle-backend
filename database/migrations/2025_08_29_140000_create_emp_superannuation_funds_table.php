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
        Schema::create('emp_superannuation_funds', function (Blueprint $table) {
            $table->id('superannuation_fund_id');
            
            // Foreign key to employees table
            $table->unsignedBigInteger('employee_id');
            
            // Core superannuation fund fields
            $table->string('fund_name', 255)->notNull(); // e.g., "HESTA Super - SGC - USI"
            $table->string('fund_code', 100)->nullable(); // Fund identifier code
            $table->string('member_number', 255)->nullable(); // Member/account number
            $table->string('usi', 255)->nullable(); // Unique Superannuation Identifier
            
            // Contribution details
            $table->enum('contribution_type', ['percentage', 'amount', 'statutory_rate'])->default('percentage');
            $table->decimal('contribution_value', 15, 2)->nullable(); // Percentage or amount value
            $table->boolean('is_statutory_rate')->default(false); // Whether using statutory rate
            
            // Fund status and control
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->date('effective_date')->nullable(); // When fund becomes effective
            $table->date('end_date')->nullable(); // When fund ends (if applicable)
            
            // Standard audit fields
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            
            // Foreign key constraint
            $table->foreign('employee_id')->references('emp_id')->on('employees')->onDelete('cascade');
            
            // Indexes
            $table->index('employee_id');
            $table->index('fund_name');
            $table->index('fund_code');
            $table->index('contribution_type');
            $table->index('status');
            $table->index('effective_date');
            $table->index('created_by');
            $table->index('updated_by');
            
            // Unique constraint - one fund per employee (can be modified based on business rules)
            $table->unique(['employee_id', 'fund_name'], 'unique_employee_fund_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emp_superannuation_funds');
    }
};
