<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoice_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_transactions', 'attachments')) {
                $table->dropColumn('attachments');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_transactions', 'attachments')) {
                $table->json('attachments')->nullable()->after('notes');
            }
        });
    }
};
