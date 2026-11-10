<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Clean up duplicates before adding unique index (keep lowest id)
        DB::statement('DELETE t1 FROM emp_assign_leave_type t1
            INNER JOIN emp_assign_leave_type t2
              ON t1.employee_id = t2.employee_id
             AND t1.company_id = t2.company_id
             AND t1.leave_type_id = t2.leave_type_id
             AND t1.id > t2.id');

        Schema::table('emp_assign_leave_type', function (Blueprint $table) {
            $table->unique(['employee_id', 'company_id', 'leave_type_id'], 'emp_assign_leave_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emp_assign_leave_type', function (Blueprint $table) {
            $table->dropUnique('emp_assign_leave_unique');
        });
    }
};


