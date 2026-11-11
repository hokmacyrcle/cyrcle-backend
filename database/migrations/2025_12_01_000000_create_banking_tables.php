<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A client business's own bank, savings and credit card accounts (not employees' pay accounts).
        if (!Schema::hasTable('business_bank_accounts')) {
            Schema::create('business_bank_accounts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('company_id')->index();
                $table->string('name', 100);
                $table->string('account_type', 20);                       // everyday, savings, credit_card
                $table->string('bsb', 10)->nullable();
                $table->string('account_number', 20)->nullable();         // for a credit card, only the last 4 digits are asked for
                $table->boolean('active')->default(true);
                $table->timestamp('last_imported_at')->nullable();
                $table->string('feed_provider', 30)->nullable();          // set when a live bank feed is connected (later)
                $table->string('feed_reference', 100)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('bank_imports')) {
            Schema::create('bank_imports', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('bank_account_id')->index();
                $table->string('file_name', 255)->nullable();
                $table->unsignedInteger('lines_read')->default(0);
                $table->unsignedInteger('lines_imported')->default(0);
                $table->unsignedInteger('lines_skipped')->default(0);     // already imported earlier
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('bank_transactions')) {
            Schema::create('bank_transactions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('bank_account_id')->index();
                $table->unsignedBigInteger('import_id')->nullable()->index();
                $table->date('txn_date')->index();
                $table->string('description', 500);
                $table->decimal('amount', 15, 2);                         // money in is positive, money out is negative, for every account type
                $table->decimal('balance_after', 15, 2)->nullable();      // only when the bank file has a balance column
                $table->string('status', 20)->default('review');          // review, done, excluded
                $table->unsignedBigInteger('matched_invoice_id')->nullable()->index();
                $table->unsignedBigInteger('invoice_payment_id')->nullable();  // the payment created on the invoice when matched
                $table->unsignedBigInteger('category_account_id')->nullable(); // chart of accounts, for spending
                $table->string('gst_code', 30)->nullable();
                $table->string('note', 500)->nullable();
                $table->string('fingerprint', 64);                        // stops the same line being imported twice
                $table->string('source', 20)->default('csv');             // csv now, feed later
                $table->timestamps();

                $table->unique(['bank_account_id', 'fingerprint']);
                $table->index(['company_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_imports');
        Schema::dropIfExists('business_bank_accounts');
    }
};
