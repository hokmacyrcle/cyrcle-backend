<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_settings', 'casual_loading')) {
                $table->decimal('casual_loading', 5, 2)->default(25.00);   // % paid on top of the hourly rate to casual staff; 0 means the rate already includes it
            }
        });
        Schema::table('pay_run_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('pay_run_lines', 'loading_rate')) {
                $table->decimal('loading_rate', 5, 2)->default(0);        // the % used for this pay, kept so a later settings change never rewrites it
            }
            if (!Schema::hasColumn('pay_run_lines', 'casual_loading')) {
                $table->decimal('casual_loading', 12, 2)->default(0);     // the loading in dollars
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_settings', 'casual_loading')) {
                $table->dropColumn('casual_loading');
            }
        });
        Schema::table('pay_run_lines', function (Blueprint $table) {
            foreach (['loading_rate', 'casual_loading'] as $c) {
                if (Schema::hasColumn('pay_run_lines', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
