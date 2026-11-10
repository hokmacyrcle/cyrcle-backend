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
        Schema::create('locations', function (Blueprint $table) {
            
                $table->id('location_id'); // Primary Key
                $table->unsignedBigInteger('company_id'); // Foreign Key
    
                $table->string('address_line_1')->nullable();
                $table->string('address_line_2')->nullable();
                $table->string('address_line_3')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('postcode')->nullable();
                $table->string('country')->nullable();
    
                $table->enum('location_status', ['Active', 'Inactive'])->default('Active');
    
                $table->timestamps();
    
                // Foreign Key Constraint
                //$table->foreign('company_id')->references('company_id')->on('companies')->onDelete('cascade');
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
