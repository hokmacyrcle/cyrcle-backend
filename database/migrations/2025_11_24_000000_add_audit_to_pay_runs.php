<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pay_runs', function (Blueprint $table) {
            if (!Schema::hasColumn('pay_runs', 'finalised_by')) {
                $table->unsignedBigInteger('finalised_by')->nullable()->after('finalised_at');
            }
            if (!Schema::hasColumn('pay_runs', 'super_paid_by')) {
                $table->unsignedBigInteger('super_paid_by')->nullable()->after('super_paid_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pay_runs', function (Blueprint $table) {
            foreach (['finalised_by', 'super_paid_by'] as $c) {
                if (Schema::hasColumn('pay_runs', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
