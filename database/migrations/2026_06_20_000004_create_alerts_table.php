<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['humidity_warn', 'humidity_crit', 'temp', 'fungus', 'silica_due', 'tamper']);
            $table->string('message');
            $table->decimal('value', 8, 2)->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'type', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
