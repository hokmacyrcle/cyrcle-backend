<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pay_run_line_items', 'payee')) {
            Schema::table('pay_run_line_items', function (Blueprint $table) {
                $table->string('payee', 80)->nullable()->after('label'); // who a deduction is paid to, shown on the payslip
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pay_run_line_items', 'payee')) {
            Schema::table('pay_run_line_items', function (Blueprint $table) {
                $table->dropColumn('payee');
            });
        }
    }
};
