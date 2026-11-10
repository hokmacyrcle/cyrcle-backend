<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('marketing_opt_in')->default(false)->after('password');
        });

        // Existing companies stay 'active'; only new firm sign-ups start 'pending'.
        Schema::table('companies', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('company_type'); // pending | active | rejected | suspended
            $table->string('state', 3)->nullable();
            $table->string('address_line')->nullable();
            $table->string('client_count_band', 20)->nullable();
            $table->string('staff_count_band', 20)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
        });

        Schema::create('email_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_codes');
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['status', 'state', 'address_line', 'client_count_band', 'staff_count_band', 'terms_accepted_at', 'approved_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('marketing_opt_in');
        });
    }
};
