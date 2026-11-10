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
        Schema::table('employees', function (Blueprint $table) {
            // Basic Information fields
            $table->string('emp_title')->nullable()->after('emp_id'); // Mr., Ms., Dr., etc.
            $table->date('emp_date_of_birth')->nullable()->after('emp_last_name');
            $table->string('emp_job_title')->nullable()->after('emp_date_of_birth');
            
            // Contact Information fields
            $table->string('emp_address_search')->nullable()->after('emp_job_title');
            $table->boolean('emp_xero_me_invitation')->default(false)->after('emp_address_search');
            
            // Emergency Contact fields
            $table->string('emp_emergency_contact_name')->nullable()->after('emp_xero_me_invitation');
            $table->string('emp_emergency_contact_relationship')->nullable()->after('emp_emergency_contact_name');
            $table->string('emp_emergency_contact_phone')->nullable()->after('emp_emergency_contact_relationship');
            $table->string('emp_emergency_contact_email')->nullable()->after('emp_emergency_contact_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'emp_title',
                'emp_date_of_birth',
                'emp_job_title',
                'emp_address_search',
                'emp_xero_me_invitation',
                'emp_emergency_contact_name',
                'emp_emergency_contact_relationship',
                'emp_emergency_contact_phone',
                'emp_emergency_contact_email',
            ]);
        });
    }
};
