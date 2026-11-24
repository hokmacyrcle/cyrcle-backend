<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A live connection between a business and one bank, made through the feed provider. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bank_feed_links')) {
            Schema::create('bank_feed_links', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('company_id')->index();
                $table->string('provider', 30);
                $table->string('connection_ref', 150);
                $table->string('institution_id', 100)->nullable();
                $table->string('institution_name', 150)->nullable();
                $table->string('status', 20)->default('pending');          // pending, connected, disconnected
                $table->date('consent_expires_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('business_bank_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('business_bank_accounts', 'feed_link_id')) {
                $table->unsignedBigInteger('feed_link_id')->nullable()->index();
            }
            if (!Schema::hasColumn('business_bank_accounts', 'bank_balance')) {
                $table->decimal('bank_balance', 15, 2)->nullable();
            }
            if (!Schema::hasColumn('business_bank_accounts', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_feed_links');
    }
};
