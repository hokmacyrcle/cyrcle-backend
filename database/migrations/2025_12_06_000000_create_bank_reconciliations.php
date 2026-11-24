<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A finished reconciliation: the bank statement's closing balance agreed with the ticked transactions. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bank_reconciliations')) {
            Schema::create('bank_reconciliations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('bank_account_id')->index();
                $table->date('statement_end');
                $table->decimal('opening_balance', 15, 2);
                $table->decimal('closing_balance', 15, 2);                 // for a credit card, what is owed is negative
                $table->unsignedInteger('line_count')->default(0);
                $table->unsignedBigInteger('finished_by')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('bank_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('bank_transactions', 'reconciliation_id')) {
                $table->unsignedBigInteger('reconciliation_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('bank_transactions', 'reconciliation_id')) {
                $table->dropColumn('reconciliation_id');
            }
        });
        Schema::dropIfExists('bank_reconciliations');
    }
};
