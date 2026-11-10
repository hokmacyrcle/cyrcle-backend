<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmployeeDesignationsTable extends Migration
{
    public function up(): void
    {
        Schema::create('employee_designations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('designation_id')->nullable(); // If linking to a master table
            $table->string('designation'); // Free-text name of the role

            $table->date('designation_start_date');
            $table->date('designation_end_date')->nullable(); // Nullable if currently active

            $table->enum('record_status', ['Active', 'Inactive'])->default('Active');

            $table->timestamps();

            // Foreign key constraints
            $table->foreign('company_id')->references('company_id')->on('companies')->onDelete('cascade');
            $table->foreign('employee_id')->references('emp_id')->on('employees')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_designations');
    }
}
