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
        // Change subaccount column from tinyint(1) to varchar(100)
        DB::statement('ALTER TABLE accounts MODIFY COLUMN subaccount VARCHAR(100) NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Change subaccount column back to tinyint(1)
        DB::statement('ALTER TABLE accounts MODIFY COLUMN subaccount TINYINT(1) NOT NULL');
    }
};
