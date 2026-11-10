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
        Schema::create('dropdowns', function (Blueprint $table) {
            $table->id();
            $table->string('type', 100);
            $table->string('name', 255);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();

            // Foreign key constraint for self-referencing parent_id
            $table->foreign('parent_id')
                  ->references('id')
                  ->on('dropdowns')
                  ->onDelete('cascade');

            // Indexes for better performance
            $table->index('type');
            $table->index('parent_id');
            $table->index(['type', 'parent_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dropdowns');
    }
};
