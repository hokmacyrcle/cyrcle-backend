<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hours worked, one row per employee per day.
        Schema::create('timesheet_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('employee_id');
            $table->date('work_date');
            $table->decimal('hours', 5, 2);
            $table->timestamps();
            $table->unique(['employee_id', 'work_date']);
        });

        // A week (Monday to Sunday) of hours for one employee. Only approved weeks flow into pay runs.
        Schema::create('timesheet_weeks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('employee_id');
            $table->date('week_start');
            $table->string('status', 10)->default('draft');   // draft | approved
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'week_start']);
        });

        // Where a pay run line's hours came from, and whether a person has checked them.
        Schema::table('pay_run_lines', function (Blueprint $table) {
            $table->string('hours_source', 12)->nullable()->after('hours');            // timesheet | estimated | manual
            $table->boolean('hours_confirmed')->default(true)->after('hours_source');  // existing lines count as confirmed
        });
    }

    public function down(): void
    {
        // Payroll records are kept: nothing is dropped on rollback.
    }
};
