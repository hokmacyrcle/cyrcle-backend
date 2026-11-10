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
        Schema::table('audit_logs', function (Blueprint $table) {
            // Add additional fields for comprehensive audit logging
            $table->string('ip_address', 45)->nullable()->after('performed_at'); // IPv6 support
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->string('url', 500)->nullable()->after('user_agent');
            $table->string('method', 10)->nullable()->after('url'); // HTTP method
            
            // Add indexes for better performance
            $table->index(['model_type', 'model_id']);
            $table->index(['action']);
            $table->index(['performed_by']);
            $table->index(['performed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Remove indexes
            $table->dropIndex(['model_type', 'model_id']);
            $table->dropIndex(['action']);
            $table->dropIndex(['performed_by']);
            $table->dropIndex(['performed_at']);
            
            // Remove added columns
            $table->dropColumn(['ip_address', 'user_agent', 'url', 'method']);
        });
    }
};
