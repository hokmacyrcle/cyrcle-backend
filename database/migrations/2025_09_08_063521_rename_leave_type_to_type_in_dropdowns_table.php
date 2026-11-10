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
        if (!Schema::hasColumn('dropdowns', 'leave_type')) {
            return;
        }

        Schema::table('dropdowns', function (Blueprint $table) {
            $table->string('type', 100)->after('id');
        });
        
        // Copy data from leave_type to type
        DB::statement('UPDATE dropdowns SET type = leave_type');
        
        Schema::table('dropdowns', function (Blueprint $table) {
            $table->dropColumn('leave_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dropdowns', function (Blueprint $table) {
            $table->string('leave_type', 100)->after('id');
        });
        
        // Copy data from type to leave_type
        DB::statement('UPDATE dropdowns SET leave_type = type');
        
        Schema::table('dropdowns', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};

