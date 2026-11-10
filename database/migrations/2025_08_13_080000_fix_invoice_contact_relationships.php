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
        // Get the first valid contact ID
        $firstContactId = DB::table('contacts')->value('contact_id');
        
        if ($firstContactId) {
            // Update all invoices with invalid sent_to values
            DB::table('invoices')
                ->whereNotIn('sent_to', function($query) {
                    $query->select('contact_id')->from('contacts');
                })
                ->update(['sent_to' => $firstContactId]);
                
            // Also update any invoices with null sent_to
            DB::table('invoices')
                ->whereNull('sent_to')
                ->update(['sent_to' => $firstContactId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration fixes data, so no rollback needed
    }
};
