<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE resources MODIFY COLUMN type ENUM('equipment', 'facility', 'consumable', 'kit') NOT NULL DEFAULT 'equipment'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE resources MODIFY COLUMN type ENUM('equipment', 'facility') NOT NULL DEFAULT 'equipment'");
    }
};
