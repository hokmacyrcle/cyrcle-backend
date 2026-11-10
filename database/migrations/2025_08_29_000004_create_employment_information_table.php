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
        Schema::create('employment_information', function (Blueprint $table) {
            $table->id('employment_info_id');
            
            // Foreign key to employees table
            $table->unsignedBigInteger('employee_id');
            
            // Employment Type (Employee/Contractor)
            $table->enum('employment_type', ['Employee', 'Contractor'])->default('Employee');
            
            // Income Type
            $table->string('income_type')->default('Salary and wages');
            
            // Employment Basis
            $table->enum('employment_basis', ['Full-time', 'Part-time', 'Casual', 'Fixed-term', 'Seasonal'])->default('Full-time');
            
            // Start Date
            $table->date('start_date');
            
            // Classification (Award classification)
            $table->string('classification')->nullable();
            
            // Pay Frequency
            $table->string('pay_frequency')->default('Fortnightly Calendar');
            
            // Employee Group
            $table->string('employee_group')->nullable();
            
            // Holiday Group
            $table->string('holiday_group')->nullable();
            
            // Include holidays in payslips
            $table->boolean('include_holidays_in_payslips')->default(false);
            
            // Ordinary Earnings Rate
            $table->string('ordinary_earnings_rate')->default('Ordinary Hours');
            
            // Authorizations
            $table->boolean('authorised_to_approve_leave')->default(false);
            $table->boolean('authorised_to_approve_timesheets')->default(false);
            
            // Authorized Fields (comma-separated or JSON format)
            $table->text('authorised_fields')->nullable(); // Comma-separated field names or JSON array
            
            // Superannuation Memberships
            $table->json('superannuation_memberships')->nullable(); // Array of super fund details
            
            // Additional fields for flexibility
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('employee_id')->references('emp_id')->on('employees')->onDelete('cascade');
            
            // Indexes
            $table->index('employee_id');
            $table->index('employment_type');
            $table->index('employment_basis');
            $table->index('start_date');
            $table->index('is_active');
            
            // Unique constraint - one employment info per employee
            $table->unique('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employment_information');
    }
};
