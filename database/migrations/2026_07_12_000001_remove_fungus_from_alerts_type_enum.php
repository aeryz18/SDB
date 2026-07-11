<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // No historical trace of the retired fungus-risk feature is wanted
        // (unlike humidity_warn/humidity_crit, which stay valid for old rows).
        // Delete first: MySQL rejects a MODIFY on a NOT NULL enum column while
        // rows still use the value being dropped from the enum.
        DB::table('alerts')->where('type', 'fungus')->delete();

        DB::statement("ALTER TABLE alerts MODIFY type ENUM('humidity_warn', 'humidity_crit', 'temp', 'silica_due', 'silica_drift', 'silica_upcoming', 'tamper') NOT NULL");
    }

    public function down(): void
    {
        // down() restores the enum value only — deleted rows are not
        // recoverable. Deliberate one-way removal per explicit "no trace" request.
        DB::statement("ALTER TABLE alerts MODIFY type ENUM('humidity_warn', 'humidity_crit', 'temp', 'fungus', 'silica_due', 'silica_drift', 'silica_upcoming', 'tamper') NOT NULL");
    }
};
