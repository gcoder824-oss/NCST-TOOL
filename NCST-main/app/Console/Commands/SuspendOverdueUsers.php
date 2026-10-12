<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:suspend-overdue-users')]
#[Description('Command description')]
class SuspendOverdueUsers extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $overdueReservations = \App\Models\Reservation::where('status', 'BORROWED')
            ->whereNotNull('due_date')
            ->where('due_date', '<=', now())
            ->get();

        foreach ($overdueReservations as $reservation) {
            $user = $reservation->user;
            if ($user && !$user->is_suspended) {
                $user->update(['is_suspended' => true]);
                $this->info("Suspended user {$user->id} due to overdue reservation {$reservation->id}");
            }
        }
    }
}
