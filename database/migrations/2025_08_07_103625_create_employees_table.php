<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmployeesTable extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id('emp_id');

            $table->string('emp_first_name');
            $table->string('emp_middle_name')->nullable();
            $table->string('emp_last_name');

            $table->enum('emp_gender', ['Male', 'Female', 'Other'])->nullable();

            $table->string('emp_email_address')->nullable();
            $table->string('emp_phone_number')->nullable();
            $table->string('emp_mobile_number')->nullable();

            $table->unsignedBigInteger('emp_company_id'); // FK to companies
            $table->enum('emp_status', ['Active', 'Resigned', 'Terminated', 'Inactive'])->default('Active');

            $table->unsignedBigInteger('emp_designation_id')->nullable(); // FK to designations

            $table->date('emp_start_date')->nullable();
            $table->date('emp_resignation_date')->nullable();
            $table->date('emp_last_working_date')->nullable();
            $table->date('emp_termination_date')->nullable();
            $table->date('emp_contract_end_date')->nullable(); // For contract employees

            $table->string('emp_tfn')->nullable(); // Tax File Number

            $table->enum('emp_work_authorization', ['Visa', 'Permanent Resident', 'Citizen'])->nullable();
            $table->string('emp_visa_subclass')->nullable(); // Required if work_authorization is Visa
            $table->date('emp_visa_expiry_date')->nullable();

            $table->enum('emp_employment_type', ['Contract', 'Part Time', 'Full Time', 'Casual'])->nullable();

            // Address
            $table->string('emp_address_line_1')->nullable();
            $table->string('emp_address_line_2')->nullable();
            $table->string('emp_address_line_3')->nullable();
            $table->string('emp_city')->nullable();
            $table->string('emp_state')->nullable();
            $table->string('emp_post_code')->nullable();
            $table->string('emp_country')->nullable();

            $table->timestamps();

            // Foreign keys
            // $table->foreign('emp_company_id')->references('company_id')->on('companies')->onDelete('cascade');
            // $table->foreign('emp_designation_id')->references('id')->on('designations')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
}