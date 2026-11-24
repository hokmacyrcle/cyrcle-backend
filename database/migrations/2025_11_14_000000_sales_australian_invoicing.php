<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sales for Australian businesses:
 *  - customer ABN, business bank details (BSB / account) for payment instructions
 *  - invoice / quote numbers that are unique per business (not across the whole system)
 *  - GST rate per line (10% or GST-free), amounts entered ex-GST, inc-GST or without GST
 *  - fractional quantities (e.g. 1.5 hours)
 *  - quote statuses that match how quotes are really used
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'bank_account_name')) {
                $table->string('bank_account_name', 100)->nullable();
                $table->string('bank_bsb', 7)->nullable();
                $table->string('bank_account_number', 9)->nullable();
            }
        });

        Schema::table('contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('contacts', 'contact_abn')) {
                $table->string('contact_abn', 11)->nullable();
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'invoice_number')) {
                $table->string('invoice_number', 30)->nullable();
                $table->string('reference', 100)->nullable();
                $table->string('amounts_are', 12)->default('exclusive'); // exclusive | inclusive | no_gst
                $table->unique(['company_id', 'invoice_number'], 'invoices_company_number_unique');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_items', 'item_gst_rate')) {
                $table->decimal('item_gst_rate', 5, 2)->default(10);
            }
        });

        Schema::table('quotes', function (Blueprint $table) {
            if (!Schema::hasColumn('quotes', 'reference')) {
                $table->string('reference', 100)->nullable();
                $table->string('amounts_are', 12)->default('exclusive');
                $table->unsignedBigInteger('invoice_id')->nullable(); // set when the quote is turned into an invoice
            }
        });

        Schema::table('quote_items', function (Blueprint $table) {
            if (!Schema::hasColumn('quote_items', 'gst_rate')) {
                $table->decimal('gst_rate', 5, 2)->default(10);
            }
        });

        // Quote numbers were unique across the whole system; they need to be unique per business.
        try {
            Schema::table('quotes', function (Blueprint $table) {
                $table->dropUnique('quotes_quote_number_unique');
            });
        } catch (\Throwable $e) {
            // index already gone
        }
        try {
            Schema::table('quotes', function (Blueprint $table) {
                $table->unique(['company_id', 'quote_number'], 'quotes_company_number_unique');
            });
        } catch (\Throwable $e) {
            // already there
        }

        // Quantities such as 1.5 hours, and the quote statuses used in the app.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE invoice_items MODIFY item_quantity DECIMAL(10,2) NOT NULL DEFAULT 1');
            DB::statement('ALTER TABLE quote_items MODIFY quantity DECIMAL(10,2) NOT NULL DEFAULT 1');
            DB::statement("ALTER TABLE quotes MODIFY status ENUM('Pending','Accepted','Closed','Rejected','Draft','Sent','Declined','Invoiced') NOT NULL DEFAULT 'Draft'");
        }
    }

    public function down(): void
    {
        // Additive migration: nothing is removed on rollback so no invoicing data is lost.
    }
};
