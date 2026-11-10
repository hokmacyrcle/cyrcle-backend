<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update existing data to convert text values to Yes/No
        DB::statement("UPDATE accounts SET subaccount = CASE 
            WHEN subaccount IS NULL OR subaccount = '' THEN 'No'
            ELSE 'Yes'
        END");

        // Change the column type to enum
        DB::statement("ALTER TABLE accounts MODIFY COLUMN subaccount ENUM('Yes', 'No') NOT NULL DEFAULT 'No'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Change back to varchar
        DB::statement("ALTER TABLE accounts MODIFY COLUMN subaccount VARCHAR(100) NOT NULL");
    }
};
