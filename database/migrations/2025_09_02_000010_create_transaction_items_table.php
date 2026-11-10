<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('product_service_id')->nullable();
            $table->text('description')->nullable();
            $table->decimal('quantity', 18, 2)->default(0);
            $table->decimal('rate', 18, 2)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->decimal('gst', 18, 2)->default(0);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();

            $table->timestamps();

            $table->foreign('company_id')
                ->references('company_id')
                ->on('companies')
                ->onDelete('cascade');
            $table->foreign('transaction_id')
                ->references('id')
                ->on('transactions')
                ->onDelete('cascade');

            $table->index('company_id');
            $table->index('product_service_id');
            $table->index('customer_id');
            $table->index('class_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
    }
};


