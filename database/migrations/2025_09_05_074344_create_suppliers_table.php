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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->bigIncrements('supplier_id');
            
            // Basic Information
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->string('title', 50)->nullable();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 50)->nullable();
            $table->string('supplier_display_name', 255);
            $table->string('company_name', 255)->nullable();
            
            // Contact Information
            $table->string('email', 255)->nullable();
            $table->string('phone_no', 20)->nullable();
            $table->string('mobile_number', 20)->nullable();
            $table->string('fax', 20)->nullable();
            $table->string('other', 255)->nullable();
            $table->string('website', 255)->nullable();
            
            // Address Information
            $table->string('street_address1', 255)->nullable();
            $table->string('street_address_2', 255)->nullable();
            $table->string('city_state', 255)->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('country', 100)->nullable();
            
            // Additional Information
            $table->text('note')->nullable();
            $table->string('taxes_number', 50)->nullable();
            $table->decimal('expense_rates_billing_rate', 10, 2)->nullable();
            $table->unsignedBigInteger('payment_terms_id')->nullable();
            $table->string('account_no', 50)->nullable();
            $table->enum('create_batch_payments', ['Yes', 'No'])->default('No');
            
            // Banking Information
            $table->string('bsb', 20)->nullable();
            $table->string('bank_account_name', 255)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('statement_text', 255)->nullable();
            
            // Financial Information
            $table->unsignedBigInteger('default_expense_cat_id')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0.00);
            $table->date('opening_balance_date')->nullable();
            
            // Company Context
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            
            $table->timestamps();
            
            // Foreign Key Constraints
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('set null');
            $table->foreign('payment_terms_id')->references('id')->on('payment_terms')->onDelete('set null');
            $table->foreign('default_expense_cat_id')->references('id')->on('expense_categories')->onDelete('set null');
            $table->foreign('company_id')->references('company_id')->on('companies')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index(['company_id', 'supplier_display_name']);
            $table->index('email');
            $table->index('phone_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
