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
        Schema::create('operational_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_key', 180)->unique();
            $table->string('type', 40)->index();
            $table->string('severity', 16)->default('info');
            $table->string('title', 160);
            $table->text('message');
            $table->string('action_url', 255)->nullable();
            $table->json('context')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operational_alerts');
    }
};
