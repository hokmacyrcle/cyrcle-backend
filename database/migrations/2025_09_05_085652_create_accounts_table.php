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
        Schema::create('accounts', function (Blueprint $table) {
            $table->bigIncrements('account_id');
            $table->string('account_name', 255);
            $table->string('account_type', 255);
            $table->string('detail_type', 255);
            $table->enum('subaccount', ['Yes', 'No'])->default('No');
            $table->unsignedBigInteger('parent_account')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            
            // Add foreign key constraints
            $table->foreign('company_id')->references('company_id')->on('companies')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('parent_account')->references('id')->on('account_dropdowns')->onDelete('set null');
            
            // Add indexes
            $table->index('company_id');
            $table->index('account_type');
            $table->index('detail_type');
            $table->index('parent_account');
            
            // Add unique constraint for account_name per company
            $table->unique(['account_name', 'company_id'], 'unique_account_name_per_company');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};