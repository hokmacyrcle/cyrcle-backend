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
        Schema::create('emp_assign_leave_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('leave_type_id');
            $table->string('leave_cat', 100);
            $table->unsignedBigInteger('leave_calculation_method_id');
            $table->decimal('hours_accured_annually', 8, 2)->default(0);
            $table->decimal('openning_balance', 8, 2)->default(0);
            $table->decimal('hours_employee_works_fortnightly', 8, 2)->default(0);
            $table->enum('termination_unused_balnce', ['Not Paid Out', 'Paid_out'])->default('Not Paid Out');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('company_id');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('employee_id')
                  ->references('emp_id')
                  ->on('employees')
                  ->onDelete('cascade');

            $table->foreign('company_id')
                  ->references('company_id')
                  ->on('companies')
                  ->onDelete('cascade');

            $table->foreign('created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->foreign('updated_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            // Indexes for better performance
            $table->index(['employee_id', 'company_id']);
            $table->index('leave_type_id');
            $table->index('leave_calculation_method_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emp_assign_leave_type');
    }
};
