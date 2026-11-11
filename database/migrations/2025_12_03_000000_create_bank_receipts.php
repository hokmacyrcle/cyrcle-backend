<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Receipt files (photos or PDFs) kept for a business, with the details typed in for each. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bank_receipts')) {
            return;
        }
        Schema::create('bank_receipts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('file_path', 255);                       // on the private disk, never public
            $table->string('original_name', 255);
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->string('description', 255)->nullable();         // supplier or what it was for
            $table->date('receipt_date')->nullable();
            $table->decimal('amount', 15, 2)->nullable();           // total paid, GST included
            $table->decimal('gst', 15, 2)->nullable();
            $table->unsignedBigInteger('bank_account_id')->nullable();   // the account it was paid from
            $table->unsignedBigInteger('transaction_id')->nullable();    // reserved: the bank line it belongs to
            $table->unsignedBigInteger('category_account_id')->nullable(); // reserved: chart of accounts
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_receipts');
    }
};
