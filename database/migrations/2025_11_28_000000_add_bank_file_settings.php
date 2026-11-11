<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_settings', 'bank_code')) {
                $table->string('bank_code', 3)->nullable();            // the bank's 3 letter code, for example WBC
            }
            if (!Schema::hasColumn('payroll_settings', 'bank_user_id')) {
                $table->string('bank_user_id', 6)->nullable();         // the 6 digit user ID the bank issues for bulk payments
            }
            if (!Schema::hasColumn('payroll_settings', 'bank_balancing')) {
                $table->boolean('bank_balancing')->default(true);      // add the matching debit line some banks need
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            foreach (['bank_code', 'bank_user_id', 'bank_balancing'] as $c) {
                if (Schema::hasColumn('payroll_settings', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
