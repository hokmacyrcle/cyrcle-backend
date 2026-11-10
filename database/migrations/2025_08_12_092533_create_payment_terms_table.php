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
        Schema::create('payment_terms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->enum('due_calculation_method', ['fixed_days', 'certain_day'])->default('fixed_days');
            $table->integer('fixed_days_value')->nullable(); // Number of days
            $table->integer('certain_day_value')->nullable(); // Day of month (1-31)
            $table->integer('next_month_threshold_days')->nullable(); // Days before due date to move to next month
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_terms');
    }
};
