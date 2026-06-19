<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('warn_humidity')->default(35);
            $table->unsignedSmallInteger('crit_humidity')->default(45);
            $table->decimal('temp_min', 5, 1)->nullable();
            $table->decimal('temp_max', 5, 1)->nullable();
            $table->boolean('fungus_alerts_enabled')->default(true);
            $table->boolean('protection_mode')->default(false);
            $table->string('door_field')->default('door');
            $table->timestamp('silica_last_replaced_at')->nullable();
            $table->unsignedSmallInteger('silica_interval_days')->default(90);
            $table->json('notify_emails')->nullable();
            $table->unsignedSmallInteger('alert_cooldown_minutes')->default(30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_settings');
    }
};
