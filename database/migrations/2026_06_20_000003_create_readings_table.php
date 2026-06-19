<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->decimal('temperature', 5, 2)->nullable();
            $table->decimal('humidity', 5, 2)->nullable();
            $table->string('status')->nullable();
            $table->string('door_state')->nullable();
            $table->timestamp('recorded_at')->useCurrent();

            $table->index(['device_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readings');
    }
};
