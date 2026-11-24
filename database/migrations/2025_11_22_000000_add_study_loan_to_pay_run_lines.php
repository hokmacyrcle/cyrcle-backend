<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The study and training loan repayment inside a line's tax. It is part of the tax withheld, kept separately
        // because payslips and Single Touch Payroll report it on its own line.
        if (!Schema::hasColumn('pay_run_lines', 'study_loan')) {
            Schema::table('pay_run_lines', function (Blueprint $table) {
                $table->decimal('study_loan', 12, 2)->default(0)->after('tax');
            });
        }
    }

    public function down(): void
    {
        // Payroll records are kept: nothing is dropped on rollback.
    }
};
