<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Resource;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TransactionService
{
    /**
     * Create a new reservation request for an equipment.
     */
    public function requestEquipment(int $userId, int $resourceId, int $quantity = 1, string $purpose = null, string $duration = null, string $remarks = null)
    {
        $user = \App\Models\User::find($userId);
        
        // 1. Check if user is already suspended
        if ($user && $user->is_suspended) {
            $hasDamaged = \App\Models\Reservation::where('user_id', $user->id)
                ->where('status', 'RETURNED')
                ->where('remarks', 'like', '%damaged%')
                ->exists();
                
            if ($hasDamaged) {
                throw new \Exception("You are suspended because you returned a damaged item. Please coordinate with the admin to resolve this, otherwise your school credentials/documents will be put on hold.");
            }
            throw new \Exception("You are suspended due to an overdue item. Please return the overdue item first.");
        }

        // 2. Dynamic check: check if any item is overdue even if cron hasn't suspended them yet
        $hasDynamicOverdue = \App\Models\Reservation::where('user_id', $userId)
            ->where('status', 'BORROWED')
            ->whereNotNull('due_date')
            ->where('due_date', '<=', Carbon::now())
            ->exists();

        if ($hasDynamicOverdue) {
            // Suspend them right now
            if ($user) $user->update(['is_suspended' => true]);
            throw new \Exception("You are suspended due to an overdue item. Please return the overdue item first.");
        }

        $resource = Resource::findOrFail($resourceId);

        if ($resource->stock < $quantity) {
            throw new \Exception("This equipment does not have enough stock (Only {$resource->stock} available).");
        }

        $transactionId = 'TXN-' . date('Y') . '-' . strtoupper(Str::random(6));
        $qrPassCode = 'NCST-' . $transactionId;

        $reservation = Reservation::create([
            'transaction_id' => $transactionId,
            'user_id' => $userId,
            'resource_id' => $resourceId,
            'quantity' => $quantity,
            'purpose' => $purpose,
            'duration' => $duration,
            'status' => 'PENDING_APPROVAL',
            'pass_qr_token' => $qrPassCode,
            'requested_at' => Carbon::now(),
            'remarks' => $remarks,
        ]);

        return $reservation;
    }

    /**
     * Approve a pending reservation request.
     */
    public function approveTransaction(int $reservationId)
    {
        $reservation = Reservation::findOrFail($reservationId);

        if ($reservation->status !== 'PENDING_APPROVAL') {
            throw new \Exception('Transaction is not in pending state.');
        }

        $resource = $reservation->resource;

        if ($resource->stock < $reservation->quantity) {
            throw new \Exception('This equipment is currently out of stock.');
        }

        // Lock quantity of stock
        $resource->decrement('stock', $reservation->quantity);

        $reservation->update([
            'status' => 'APPROVED_AWAITING_PICKUP',
            'approved_at' => Carbon::now(),
        ]);

        return $reservation;
    }

    /**
     * Decline a pending reservation request.
     */
    public function declineTransaction(int $reservationId, string $remarks)
    {
        $reservation = Reservation::findOrFail($reservationId);

        if ($reservation->status !== 'PENDING_APPROVAL') {
            throw new \Exception('Transaction is not in pending state.');
        }

        $reservation->update([
            'status' => 'REJECTED',
            'remarks' => $remarks
        ]);

        return $reservation;
    }

    /**
     * Return a borrowed equipment.
     */
    public function returnTransaction(int $reservationId, string $condition = 'Good Condition')
    {
        $reservation = Reservation::findOrFail($reservationId);

        if ($reservation->status !== 'BORROWED') {
            throw new \Exception('Transaction is not currently borrowed.');
        }

        $resource = $reservation->resource;

        // Restore quantity of stock
        $resource->increment('stock', $reservation->quantity);

        $reservation->update([
            'status' => 'RETURNED',
            'returned_at' => Carbon::now(),
            'remarks' => $condition
        ]);

        $isDamaged = str_contains(strtolower($condition), 'damaged') || str_contains(strtolower($condition), 'bad');

        if ($reservation->user) {
            if ($isDamaged) {
                $reservation->user->update(['is_suspended' => true]);
            } elseif ($reservation->user->is_suspended) {
                $hasDamaged = \App\Models\Reservation::where('user_id', $reservation->user->id)
                    ->where('status', 'RETURNED')
                    ->where('remarks', 'like', '%damaged%')
                    ->exists();

                $hasOtherOverdue = \App\Models\Reservation::where('user_id', $reservation->user->id)
                    ->where('id', '!=', $reservation->id)
                    ->where('status', 'BORROWED')
                    ->whereNotNull('due_date')
                    ->where('due_date', '<=', Carbon::now())
                    ->exists();

                if (!$hasDamaged && !$hasOtherOverdue) {
                    $reservation->user->update(['is_suspended' => false, 'suspended_at' => null]);
                }
            }
        }

        return $reservation;
    }

    /**
     * Verify QR scan and complete the checkout transaction.
     */
    public function verifyAndRelease(string $qrPassCode)
    {
        $reservation = Reservation::where('pass_qr_token', $qrPassCode)->firstOrFail();

        if ($reservation->status !== 'APPROVED_AWAITING_PICKUP') {
            throw new \Exception('Transaction is not approved or has already been processed.');
        }

        // For demo purposes, the due date is strictly 2 minutes from checkout
        $dueDate = Carbon::now()->addMinutes(2);

        if (strtolower($reservation->resource->type) === 'consumable') {
            $reservation->update([
                'status' => 'DISPENSED',
                'borrowed_at' => Carbon::now(),
                'returned_at' => Carbon::now(),
                'remarks' => 'Dispensed',
                'pass_qr_token' => null // Invalidated
            ]);
        } else {
            // Generate a NEW token for the return process
            $newTransactionId = 'RET-' . date('Y') . '-' . strtoupper(Str::random(6));
            $newQrPassCode = 'NCST-' . $newTransactionId;

            $reservation->update([
                'status' => 'BORROWED',
                'borrowed_at' => Carbon::now(),
                'due_date' => $dueDate,
                'pass_qr_token' => $newQrPassCode // Old code is replaced and now invalid
            ]);
        }

        // In a real application, you might also log this to an immutable transaction_logs table

        return $reservation->load(['user', 'resource']);
    }

    public function rejectRelease($id, $remarks)
    {
        $reservation = Reservation::findOrFail($id);

        if ($reservation->status !== 'APPROVED_AWAITING_PICKUP') {
            throw new \Exception("Only approved reservations awaiting pickup can be rejected.");
        }

        // Revert stock
        $resource = $reservation->resource;
        $resource->stock += $reservation->quantity;
        $resource->save();

        $reservation->update([
            'status' => 'DECLINED',
            'remarks' => $remarks
        ]);

        return $reservation;
    }

    public function checkoutWalkIn(int $userId, int $resourceId, int $quantity = 1, string $purpose = null)
    {
        $resource = Resource::findOrFail($resourceId);

        if ($resource->stock < $quantity) {
            throw new \Exception("This equipment does not have enough stock (Only {$resource->stock} available).");
        }

        $transactionId = 'TXN-' . date('Y') . '-' . strtoupper(Str::random(6));
        $qrPassCode = 'NCST-' . $transactionId;

        $resource->decrement('stock', $quantity);

        $status = (strtolower($resource->type) === 'consumable') ? 'DISPENSED' : 'BORROWED';

        $reservation = Reservation::create([
            'transaction_id' => $transactionId,
            'user_id' => $userId,
            'resource_id' => $resourceId,
            'quantity' => $quantity,
            'purpose' => $purpose ?? 'Walk-in Checkout',
            'duration' => null,
            'status' => $status,
            'pass_qr_token' => $qrPassCode,
            'requested_at' => Carbon::now(),
            'approved_at' => Carbon::now(),
            'borrowed_at' => Carbon::now(),
            'due_date' => $status === 'BORROWED' ? Carbon::now()->addMinutes(2) : null,
            'is_walk_in' => true,
        ]);

        return $reservation;
    }
}
