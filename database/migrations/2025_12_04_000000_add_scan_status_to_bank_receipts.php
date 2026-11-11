<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_receipts', function (Blueprint $table) {
            if (!Schema::hasColumn('bank_receipts', 'scan_status')) {
                $table->string('scan_status', 10)->default('none');     // none, done, failed
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('bank_receipts', 'scan_status')) {
                $table->dropColumn('scan_status');
            }
        });
    }
};
