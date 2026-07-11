<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('silica_replacements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->timestamp('replaced_at');
            $table->unsignedInteger('interval_days_actual')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'replaced_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('silica_replacements');
    }
};
