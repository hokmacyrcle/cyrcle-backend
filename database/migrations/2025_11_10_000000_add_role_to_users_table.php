<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles: 'employer' (company owner/admin, full access) and 'employee'
     * (self-service only). Every existing user is a company owner, so the
     * default keeps current behaviour unchanged.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role', 20)->default('employer')->after('email')->index();
            }
            if (!Schema::hasColumn('users', 'employee_id')) {
                // Links an employee login to employees.emp_id
                $table->unsignedBigInteger('employee_id')->nullable()->after('role')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'employee_id')) {
                $table->dropColumn('employee_id');
            }
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
