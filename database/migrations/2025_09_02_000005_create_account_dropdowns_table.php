<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_dropdowns', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Type of dropdown row: 'account_type', 'detail_type', 'parent_account'
            $table->string('type');

            // Display name for the dropdown option
            $table->string('name');

            // Optional parent for hierarchical relations
            // For 'detail_type', parent_id should reference an 'account_type' row
            // For 'parent_account', you may also use parent_id to create nested groupings (optional)
            $table->unsignedBigInteger('parent_id')->nullable();

            // Company context
            $table->unsignedBigInteger('company_id');

            $table->timestamps();

            // FKs
            $table->foreign('company_id')
                ->references('company_id')
                ->on('companies')
                ->onDelete('cascade');

            $table->foreign('parent_id')
                ->references('id')
                ->on('account_dropdowns')
                ->nullOnDelete();

            // Helpful indexes and uniqueness per company
            $table->index(['company_id', 'type']);
            $table->index('parent_id');
            $table->unique(['company_id', 'type', 'name', 'parent_id'], 'uniq_company_type_name_parent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_dropdowns');
    }
};


