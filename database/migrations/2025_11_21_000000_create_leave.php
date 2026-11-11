<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The kinds of leave a business offers, with how fast each one builds up (hours worked for 1 hour of leave).
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('code', 12);                       // annual | personal | unpaid
            $table->string('name', 40);
            $table->boolean('paid')->default(true);
            $table->decimal('accrual_hours', 6, 2)->nullable(); // 13 = 1 hour of leave for every 13 hours worked; null = does not build up
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        // Every change to a leave balance, in hours (plus = added, minus = used). The balance is the sum.
        Schema::create('leave_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->string('kind', 12);                       // accrual | taken | adjustment
            $table->decimal('hours', 8, 2);
            $table->date('entry_date');
            $table->string('note', 120)->nullable();
            $table->unsignedBigInteger('source_id')->nullable(); // the pay run (accrual) or leave request (taken) it came from
            $table->timestamps();
            $table->index(['employee_id', 'leave_type_id']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('leave_type_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('hours', 8, 2);
            $table->string('status', 10)->default('pending');  // pending | approved | declined
            $table->string('note', 200)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Payroll records are kept: nothing is dropped on rollback.
    }
};
