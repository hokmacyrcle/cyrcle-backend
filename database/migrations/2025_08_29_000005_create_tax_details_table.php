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
        Schema::create('emp_tax_details', function (Blueprint $table) {
            $table->id('tax_detail_id');
            
            // Foreign key to employees table
            $table->unsignedBigInteger('employee_id');
            
            // Tax File Number (TFN) and TFN Exemption
            $table->string('tax_file_number', 9)->nullable(); // TFN format: 123456789
            $table->enum('tfn_exemption', ['Working holiday maker', 'Student', 'Pensioner', 'Other'])->nullable();
            
            // Residency Status
            $table->enum('residency_status', ['Australian resident', 'Foreign resident'])->default('Australian resident');
            
            // Withholding Variation
            $table->boolean('has_approved_withholding_variation')->default(false);
            
            // Tax Scale
            $table->enum('tax_scale_type', ['Regular', 'No TFN', 'Working holiday maker', 'Student', 'Pensioner'])->default('Regular');
            
            // Tax Offset and Variations
            $table->boolean('has_study_training_loans')->default(false);
            $table->boolean('claim_tax_free_threshold')->default(true);
            $table->boolean('other_tax_offset_claimed')->default(false);
            $table->decimal('tax_offset_amount', 10, 2)->default(0.00); // Annual amount
            $table->boolean('increase_tax_withheld')->default(false);
            $table->decimal('additional_tax_withheld', 10, 2)->default(0.00); // Per pay period
            $table->boolean('eligible_for_leave_loading')->default(false);
            
            // Additional tax information
            $table->text('tax_notes')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('employee_id')->references('emp_id')->on('employees')->onDelete('cascade');
            
            // Indexes
            $table->index('employee_id');
            $table->index('residency_status');
            $table->index('tax_scale_type');
            $table->index('is_active');
            
            // Unique constraint - one tax detail per employee
            $table->unique('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emp_tax_details');
    }
};
