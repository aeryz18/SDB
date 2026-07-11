<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE alerts MODIFY type ENUM('humidity_warn', 'humidity_crit', 'temp', 'fungus', 'silica_due', 'silica_drift', 'silica_upcoming', 'tamper') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE alerts MODIFY type ENUM('humidity_warn', 'humidity_crit', 'temp', 'fungus', 'silica_due', 'silica_drift', 'tamper') NOT NULL");
    }
};
