<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pay_run_line_items', function (Blueprint $table) {
            if (!Schema::hasColumn('pay_run_line_items', 'allowance_type')) {
                $table->string('allowance_type', 2)->nullable();   // ATO allowance type, reported in STP
            }
            if (!Schema::hasColumn('pay_run_line_items', 'allowance_desc')) {
                $table->string('allowance_desc', 2)->nullable();   // ATO description code, only for the "other" type
            }
        });
        Schema::table('pay_run_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('pay_run_lines', 'payslip_note')) {
                $table->string('payslip_note', 200)->nullable();   // optional message printed on the payslip
            }
        });
    }

    public function down(): void
    {
        Schema::table('pay_run_line_items', function (Blueprint $table) {
            foreach (['allowance_type', 'allowance_desc'] as $c) {
                if (Schema::hasColumn('pay_run_line_items', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
        Schema::table('pay_run_lines', function (Blueprint $table) {
            if (Schema::hasColumn('pay_run_lines', 'payslip_note')) {
                $table->dropColumn('payslip_note');
            }
        });
    }
};
