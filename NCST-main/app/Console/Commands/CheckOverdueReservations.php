<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reservations:check-overdue')]
#[Description('Check for expired loans and update them to overdue')]
class CheckOverdueReservations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = now();
        
        $overdueReservations = \App\Models\Reservation::where('status', 'checked_in')
            ->where('end_time', '<', $now)
            ->get();

        foreach ($overdueReservations as $reservation) {
            $reservation->update(['status' => 'overdue']);
            $this->info("Reservation {$reservation->id} marked as overdue.");
        }

        $this->info("Checked for overdue reservations. Found: " . $overdueReservations->count());
    }
}
