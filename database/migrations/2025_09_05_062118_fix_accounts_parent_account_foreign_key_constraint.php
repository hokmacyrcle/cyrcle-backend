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
        Schema::table('accounts', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['parent_account']);
        });

        // Add new foreign key constraint to reference account_dropdowns
        DB::statement('ALTER TABLE accounts ADD CONSTRAINT accounts_parent_account_foreign FOREIGN KEY (parent_account) REFERENCES account_dropdowns(id) ON DELETE SET NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Drop the new foreign key constraint
            $table->dropForeign(['parent_account']);
        });

        // Restore the original foreign key constraint
        DB::statement('ALTER TABLE accounts ADD CONSTRAINT accounts_parent_account_foreign FOREIGN KEY (parent_account) REFERENCES accounts(account_id) ON DELETE SET NULL');
    }
};
