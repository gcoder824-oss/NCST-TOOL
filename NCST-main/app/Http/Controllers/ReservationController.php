<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Reservation;
use Illuminate\Support\Str;

class ReservationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'resource_id' => 'required|exists:resources,id',
            'start_time' => 'required|date|after_or_equal:now',
            'end_time' => 'required|date|after:start_time',
        ]);

        $user = $request->user();
        $resource = \App\Models\Resource::findOrFail($validated['resource_id']);

        // 0. Block students from reserving facilities
        if ($user->role === 'student' && $resource->type === 'facility') {
            return back()->withErrors(['error' => 'Students are only allowed to borrow equipment, not reserve facilities.']);
        }

        // 1. Block users with overdue items or who are suspended
        if ($user->is_suspended) {
            $hasDamaged = Reservation::where('user_id', $user->id)
                ->where('status', 'RETURNED')
                ->where('remarks', 'like', '%damaged%')
                ->exists();
                
            if ($hasDamaged) {
                return back()->withErrors(['error' => 'You are suspended because you returned a damaged item. Please coordinate with the admin to resolve this, otherwise your school credentials/documents will be put on hold.']);
            }
            return back()->withErrors(['error' => 'You are suspended due to an overdue item. Please return the overdue item first.']);
        }

        $hasDynamicOverdue = Reservation::where('user_id', $user->id)
            ->where('status', 'BORROWED')
            ->whereNotNull('due_date')
            ->where('due_date', '<=', \Carbon\Carbon::now())
            ->exists();

        $hasOverdueStatus = Reservation::where('user_id', $user->id)
            ->where('status', 'overdue')
            ->exists();

        if ($hasDynamicOverdue || $hasOverdueStatus) {
            if (!$user->is_suspended) {
                $user->update(['is_suspended' => true]);
            }
            return back()->withErrors(['error' => 'You cannot make a reservation because you have overdue items.']);
        }

        // 2. Reject if overlapping with an existing approved booking
        $isOverlapping = Reservation::where('resource_id', $validated['resource_id'])
            ->whereIn('status', ['approved', 'checked_in'])
            ->where(function ($query) use ($validated) {
                $query->whereBetween('start_time', [$validated['start_time'], $validated['end_time']])
                      ->orWhereBetween('end_time', [$validated['start_time'], $validated['end_time']])
                      ->orWhere(function ($q) use ($validated) {
                          $q->where('start_time', '<=', $validated['start_time'])
                            ->where('end_time', '>=', $validated['end_time']);
                      });
            })
            ->exists();

        if ($isOverlapping) {
            return back()->withErrors(['error' => 'The selected time window overlaps with an existing approved booking.']);
        }

        // Create reservation
        $reservation = Reservation::create([
            'user_id' => $user->id,
            'resource_id' => $validated['resource_id'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'status' => 'pending',
            'pass_qr_token' => Str::random(32),
        ]);

        return redirect()->back()->with('success', 'Reservation created successfully!');
    }

    public function generateQrPass(Reservation $reservation)
    {
        // Only allow generation for approved reservations
        if (!in_array($reservation->status, ['APPROVED_AWAITING_PICKUP', 'BORROWED'])) {
            abort(403, 'QR Pass is only available for approved reservations.');
        }

        // Generate a dynamic QR code based on the reservation's unique token
        $qrCode = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(300)
            ->style('round')
            ->generate($reservation->pass_qr_token);

        return response($qrCode)->header('Content-type', 'image/svg+xml');
    }

    public function verifyKioskPass($passToken)
    {
        $reservation = Reservation::with(['user', 'resource'])
            ->where('pass_qr_token', $passToken)
            ->where('status', 'approved')
            ->firstOrFail();

        return response()->json([
            'reservation' => $reservation,
            'user' => $reservation->user
        ]);
    }

    public function processCheckout(Request $request)
    {
        $validated = $request->validate([
            'reservation_id' => 'required|exists:reservations,id',
            'asset_code' => 'required|string',
            'pass_qr_token' => 'required|string',
        ]);

        $reservation = Reservation::with('resource')
            ->where('id', $validated['reservation_id'])
            ->where('pass_qr_token', $validated['pass_qr_token'])
            ->where('status', 'approved')
            ->firstOrFail();

        if ($reservation->resource->asset_code !== $validated['asset_code']) {
            return response()->json(['message' => 'Asset code mismatch'], 422);
        }

        // Check in logic
        $reservation->update(['status' => 'checked_in']);
        $reservation->resource->update(['status' => 'borrowed']);

        return response()->json(['message' => 'Checkout successful']);
    }
}
