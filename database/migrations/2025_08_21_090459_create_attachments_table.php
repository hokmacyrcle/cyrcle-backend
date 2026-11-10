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
        Schema::create('attachments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type'); // Invoice, Transaction, Quotes, Company, Bill
            $table->unsignedBigInteger('type_id');
            $table->string('image_url');
            $table->string('original_name'); // Original filename
            $table->string('filename'); // Stored filename
            $table->bigInteger('size'); // File size in bytes
            $table->string('mime_type'); // File MIME type
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['type', 'type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
