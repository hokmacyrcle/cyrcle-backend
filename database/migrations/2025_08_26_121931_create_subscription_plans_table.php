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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id('plan_id');
            $table->string('plan_name')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0.00);
            $table->string('billing_cycle')->default('monthly'); // monthly, yearly, etc.
            $table->integer('billing_cycle_days')->default(30);
            $table->integer('max_users')->nullable(); // null means unlimited
            $table->integer('max_companies')->nullable(); // null means unlimited
            $table->integer('max_invoices_per_month')->nullable(); // null means unlimited
            $table->integer('max_quotes_per_month')->nullable(); // null means unlimited
            $table->json('features')->nullable(); // JSON array of features
            $table->boolean('is_active')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            // Indexes
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
