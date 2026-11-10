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
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('code', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('company_id');
            $table->timestamps();
            
            // Foreign Key Constraints
            $table->foreign('company_id')->references('company_id')->on('companies')->onDelete('cascade');
            
            // Indexes
            $table->index(['company_id', 'name']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
