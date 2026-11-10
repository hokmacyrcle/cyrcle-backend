<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Pay details on the employee, and room for the tax file number to be stored encrypted. Additive. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('employment_information', function (Blueprint $table) {
            $table->string('pay_basis', 10)->nullable()->after('pay_frequency');          // hourly | salary
            $table->decimal('hourly_rate', 10, 2)->nullable()->after('pay_basis');
            $table->decimal('annual_salary', 12, 2)->nullable()->after('hourly_rate');
            $table->decimal('hours_per_week', 5, 2)->nullable()->after('annual_salary');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->index('emp_company_id', 'employees_company_idx');
        });

        // An encrypted value is much longer than 9 characters.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE emp_tax_details MODIFY tax_file_number TEXT NULL');
        }
    }

    public function down(): void
    {
        // Intentionally left as a no-op: never drop payroll data on rollback.
    }
};
