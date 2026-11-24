<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_reminders')) {
            return;
        }
        Schema::create('invoice_reminders', function (Blueprint $table) {
            $table->bigIncrements('reminder_id');
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('invoice_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('channel', 20)->default('manual');   // manual = the person sent it from their own email; email = sent by the app (later)
            $table->string('tone', 20);                         // friendly, follow_up, final
            $table->string('subject', 200);
            $table->text('body');
            $table->unsignedInteger('days_overdue')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_reminders');
    }
};
