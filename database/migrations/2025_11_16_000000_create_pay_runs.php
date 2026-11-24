<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pay_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('reference', 20);
            $table->string('frequency', 12);
            $table->date('period_start');
            $table->date('period_end');
            $table->date('pay_date');
            $table->date('super_due_date')->nullable();
            $table->string('status', 12)->default('draft');   // draft | finalised
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_tax', 14, 2)->default(0);
            $table->decimal('total_super', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->timestamp('finalised_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'reference']);
        });

        Schema::create('pay_run_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pay_run_id')->index();
            $table->unsignedBigInteger('employee_id')->index();
            $table->string('employee_name', 200);
            $table->string('pay_basis', 10);                    // hourly | salary
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('annual_salary', 12, 2)->nullable();
            $table->decimal('hours', 8, 2)->nullable();
            $table->decimal('gross', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->string('tax_method', 20)->nullable();       // scale1 | scale2 | no_tfn | manual
            $table->string('tax_status', 20)->default('auto');  // auto | review | manual_required
            $table->string('tax_note', 255)->nullable();
            $table->boolean('tax_overridden')->default(false);
            $table->boolean('tax_confirmed')->default(false);
            $table->decimal('super_amount', 12, 2)->default(0);
            $table->decimal('net', 12, 2)->default(0);
            $table->string('super_fund_name', 255)->nullable();
            $table->string('super_member_number', 255)->nullable();
            $table->timestamps();
            $table->unique(['pay_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        // Intentionally left as a no-op: never drop payroll records on rollback.
    }
};
