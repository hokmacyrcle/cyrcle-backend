<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A record that the employer says they paid the super for this run. Cyrcle does not send the money.
        Schema::table('pay_runs', function (Blueprint $table) {
            $table->timestamp('super_paid_at')->nullable()->after('super_due_date');
        });
    }

    public function down(): void
    {
        // Payroll records are kept: nothing is dropped on rollback.
    }
};
