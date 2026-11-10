<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCompaniesTable extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id('company_id'); // Auto Generated Primary Key
            $table->string('company_name');

            $table->string('company_abn')->nullable();
            $table->string('company_acn')->nullable();

            // Foreign key to company locations
            $table->unsignedBigInteger('company_primary_location_id')->nullable();

            $table->boolean('gst_registered')->default(false);
            $table->string('gst_number')->nullable();
            $table->date('gst_registration_start_date')->nullable();

            $table->date('company_registration_date')->nullable();

            // Foreign key to employees table (primary contact)
            $table->unsignedBigInteger('primary_contact_person')->nullable();

            $table->enum('company_type', ['Subscriber', 'Client']);
            $table->unsignedBigInteger('subscriber_id')->nullable(); // Applicable if company_type = Client

            $table->timestamps();

            // Foreign Key Constraints
            // $table->foreign('company_primary_location_id')
            //       ->references('id')->on('locations')->onDelete('set null');

            // $table->foreign('primary_contact_person')
            //       ->references('id')->on('employees')->onDelete('set null');

            // $table->foreign('subscriber_id')
            //       ->references('company_id')->on('companies')->onDelete('set null'); // Self-reference
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
}










