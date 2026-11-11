<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('recurring_invoices')) {
            Schema::create('recurring_invoices', function (Blueprint $table) {
                $table->bigIncrements('recurring_invoice_id');
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('source_invoice_id');          // the invoice that gets copied each time
                $table->string('frequency', 20);                         // weekly, fortnightly, monthly, quarterly, yearly
                $table->date('start_date');                              // first scheduled date; later dates count from here
                $table->unsignedInteger('runs')->default(0);             // drafts created so far
                $table->date('next_date');                               // the next draft is created on this date
                $table->date('end_date')->nullable();                    // stop after this date (optional)
                $table->boolean('active')->default(true);
                $table->timestamp('last_run_at')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasColumn('invoices', 'recurring_invoice_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->unsignedBigInteger('recurring_invoice_id')->nullable()->index();   // set on drafts the schedule created
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'recurring_invoice_id')) {
            Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('recurring_invoice_id'));
        }
        Schema::dropIfExists('recurring_invoices');
    }
};
