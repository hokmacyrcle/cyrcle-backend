<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContactsTable extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {

            $table->id('contact_id'); // Primary Key
            $table->unsignedBigInteger('company_id')->nullable();
            $table->enum('contact_type', ['Individual', 'Company', 'Sole Trader']);
            // Individual / Sole Trader
            $table->string('contact_first_name')->nullable();
            $table->string('contact_middle_name')->nullable();
            $table->string('contact_last_name')->nullable();
            // Company
            $table->string('contact_company_name')->nullable();
            $table->string('contact_phone_number')->nullable();
            $table->string('contact_mobile_number')->nullable();
            $table->string('contact_email_address')->nullable();
            $table->string('contact_address_line_1')->nullable();
            $table->string('contact_address_line_2')->nullable();
            $table->string('contact_address_line_3')->nullable();
            $table->string('contact_city')->nullable();
            $table->string('contact_state')->nullable();
            $table->string('contact_post_code')->nullable();
            $table->string('contact_country')->nullable();
            $table->enum('contact_category', ['Supplier', 'Vendor', 'Other'])->nullable();
            $table->enum('contact_status', ['Active', 'Inactive'])->default('Active');
            $table->boolean('subscriber_client')->default(false);
            $table->unsignedBigInteger('subscriber_id')->nullable();
            $table->timestamps();
            // Constraints
            //$table->foreign('subscriber_id')->references('id')->on('subscribers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
}

