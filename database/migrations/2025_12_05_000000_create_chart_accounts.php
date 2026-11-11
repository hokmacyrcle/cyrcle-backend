<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business's chart of accounts: the list of accounts money is sorted into.
 * (The older `accounts` table depends on dropdown rows that only some businesses have, so it is left alone.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chart_accounts')) {
            return;
        }
        Schema::create('chart_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->index();
            $table->string('code', 10)->nullable();
            $table->string('name', 150);
            $table->string('type', 12);                          // asset, liability, equity, income, expense
            $table->string('detail', 40);                        // for example Fixed asset, Operating expense
            $table->string('gst_code', 10)->default('none');     // gst, free, input, capital, none
            $table->string('description', 255)->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('locked')->default(false);           // set up by Cyrcle, kept so reports keep working
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_accounts');
    }
};
