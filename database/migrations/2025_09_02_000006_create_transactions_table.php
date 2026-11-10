<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('company_id');

            $table->enum('transaction_type', [
                'bill',
                'expense',
                'purchase_order',
                'cheque',
                'time_activity',
            ]);

            $table->date('transaction_date');
            $table->string('reference_no', 100)->nullable();
            $table->string('status', 50)->default('Draft');
            $table->decimal('total_amount', 18, 2)->default(0);

            $table->timestamps();

            $table->foreign('company_id')
                ->references('company_id')
                ->on('companies')
                ->onDelete('cascade');

            $table->index(['company_id', 'transaction_type']);
            $table->index('transaction_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};


