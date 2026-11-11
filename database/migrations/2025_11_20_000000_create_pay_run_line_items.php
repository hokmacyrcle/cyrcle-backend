<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extra amounts on one employee's line of a pay run: allowances, bonuses, overtime, reimbursements, deductions.
        Schema::create('pay_run_line_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pay_run_line_id')->index();
            $table->string('kind', 16);                 // allowance | bonus | overtime | leave | reimbursement | deduction
            $table->string('label', 60);
            $table->decimal('hours', 8, 2)->nullable();
            $table->decimal('rate', 10, 2)->nullable();
            $table->decimal('amount', 12, 2);
            $table->boolean('counts_for_super')->default(false);
            $table->unsignedBigInteger('source_id')->nullable();   // e.g. the leave application a paid-leave item came from
            $table->timestamps();
        });

        Schema::table('pay_run_lines', function (Blueprint $table) {
            $table->decimal('base_pay', 12, 2)->default(0)->after('hours_confirmed');       // ordinary pay, before any items
            $table->decimal('reimbursements', 12, 2)->default(0)->after('base_pay');
            $table->decimal('deductions', 12, 2)->default(0)->after('reimbursements');
        });

        // Lines made before items existed had no items, so their ordinary pay is their gross.
        DB::statement('UPDATE pay_run_lines SET base_pay = gross');
    }

    public function down(): void
    {
        // Payroll records are kept: nothing is dropped on rollback.
    }
};
