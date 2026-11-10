<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending')->after('pay_period_hours');
            $table->text('reject_reason')->nullable()->after('status');
            $table->unsignedBigInteger('approved_by')->nullable()->after('reject_reason');
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['status', 'reject_reason', 'approved_by', 'approved_at']);
        });
    }
};


