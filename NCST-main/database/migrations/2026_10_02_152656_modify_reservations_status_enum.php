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
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE reservations MODIFY COLUMN status ENUM('PENDING_APPROVAL', 'APPROVED_AWAITING_PICKUP', 'BORROWED', 'RETURNED', 'REJECTED', 'ACTIVE', 'DECLINED', 'DISPENSED') DEFAULT 'PENDING_APPROVAL'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE reservations MODIFY COLUMN status ENUM('PENDING_APPROVAL', 'APPROVED_AWAITING_PICKUP', 'BORROWED', 'RETURNED', 'REJECTED') DEFAULT 'PENDING_APPROVAL'");
    }
};
