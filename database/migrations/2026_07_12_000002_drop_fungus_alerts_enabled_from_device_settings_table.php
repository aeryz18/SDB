<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_settings', function (Blueprint $table) {
            $table->dropColumn('fungus_alerts_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('device_settings', function (Blueprint $table) {
            $table->boolean('fungus_alerts_enabled')->default(true);
        });
    }
};
