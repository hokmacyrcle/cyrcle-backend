<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('employees', 'emp_cessation_reason')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('emp_cessation_reason', 1)->nullable(); // ATO code for why employment ended, used in STP
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'emp_cessation_reason')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('emp_cessation_reason');
            });
        }
    }
};
