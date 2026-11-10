<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_purchase_orders', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->date('order_date')->nullable();
            $table->unsignedBigInteger('ship_to_customer_id')->nullable();
            $table->text('shipping_address')->nullable();
            $table->text('mailing_address')->nullable();
            $table->string('ship_via', 100)->nullable();
            $table->string('email', 255)->nullable();

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
            $table->index('supplier_id');
            $table->index('order_date');
            $table->unique(['transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_purchase_orders');
    }
};


