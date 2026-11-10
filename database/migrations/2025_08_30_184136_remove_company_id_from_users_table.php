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
        Schema::table('users', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['company_id']);
            
            // Drop the company_id column
            $table->dropColumn('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Re-add the company_id column
            $table->unsignedBigInteger('company_id')->nullable()->after('id');
            
            // Re-add the foreign key constraint
            $table->foreign('company_id')->references('company_id')->on('companies')->onDelete('set null');
        });
    }
};
