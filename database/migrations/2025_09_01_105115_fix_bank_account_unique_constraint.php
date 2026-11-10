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
        // Check if the problematic constraint exists and drop it if it does
        $indexExists = DB::select("
            SELECT COUNT(*) as count 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = 'emp_bank_accounts' 
            AND index_name = 'unique_primary_account_per_employee'
        ")[0]->count;

        if ($indexExists > 0) {
            DB::statement('ALTER TABLE emp_bank_accounts DROP INDEX unique_primary_account_per_employee');
        }

        // For MariaDB/MySQL, we'll use a simpler approach
        // Create a unique index on (employee_id, is_primary_account) but only for primary accounts
        // We'll do this by creating a unique index on a computed column
        
        // First, add a computed column that only has values when is_primary_account = 1
        
        DB::statement('
            ALTER TABLE emp_bank_accounts 
            ADD COLUMN primary_account_key VARCHAR(50) GENERATED ALWAYS AS 
            (CASE WHEN is_primary_account = 1 THEN CONCAT(employee_id, "_", is_primary_account) ELSE NULL END) VIRTUAL
        ');

        // Create unique index on the computed column (this will only enforce uniqueness for primary accounts)
        DB::statement('
            CREATE UNIQUE INDEX unique_primary_account_per_employee 
            ON emp_bank_accounts (primary_account_key)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the unique index
        DB::statement('DROP INDEX unique_primary_account_per_employee ON emp_bank_accounts');

        // Drop the computed column
        DB::statement('ALTER TABLE emp_bank_accounts DROP COLUMN primary_account_key');

        // Recreate the old constraint (this was the problematic one)
        DB::statement('
            ALTER TABLE emp_bank_accounts 
            ADD CONSTRAINT unique_primary_account_per_employee 
            UNIQUE (employee_id, is_primary_account)
        ');
    }
};
