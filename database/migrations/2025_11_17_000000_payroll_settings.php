<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->unique();
            $table->decimal('super_rate', 5, 2)->default(12.00);
            $table->string('default_pay_frequency', 12)->default('Fortnightly');
            $table->decimal('default_hours_per_week', 5, 2)->default(38.00);
            $table->timestamps();
        });

        // The super rate a run was calculated at, so changing Settings later never rewrites a past run.
        Schema::table('pay_runs', function (Blueprint $table) {
            $table->decimal('super_rate', 5, 2)->default(12.00)->after('status');
        });
    }

    public function down(): void
    {
        // Payroll records are kept: nothing is dropped on rollback.
    }
};
