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
            // Drop the unique index on phone first
            $table->dropUnique('users_phone_unique');
            
            // Add country_code field
            $table->string('country_code', 5)->nullable()->after('phone');
            
            // Change phone field to bigInteger
            $table->bigInteger('phone')->nullable()->change();
            
            // Add unique index on combination of country_code and phone
            $table->unique(['country_code', 'phone'], 'users_country_phone_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the combined unique index
            $table->dropUnique('users_country_phone_unique');
            
            // Change phone back to string
            $table->string('phone', 20)->nullable()->change();
            
            // Drop country_code field
            $table->dropColumn('country_code');
            
            // Re-add the original unique index
            $table->unique('phone', 'users_phone_unique');
        });
    }
};
