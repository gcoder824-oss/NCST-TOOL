<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TransactionService;

class TransactionController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    public function requestEquipment(Request $request)
    {
        \Log::info("Equipment request hit", $request->all());

        $request->validate([
            'resource_id' => 'nullable|exists:resources,id',
            'quantity' => 'required|integer|min:1',
            'purpose' => 'required|string',
            'duration' => 'required|string',
        ]);

        $user = auth()->user();
        $hasDynamicOverdue = \App\Models\Reservation::where('user_id', $user->id)
            ->where('status', 'BORROWED')
            ->whereNotNull('due_date')
            ->where('due_date', '<=', \Carbon\Carbon::now())
            ->exists();

        if ($user->is_suspended || $hasDynamicOverdue) {
            if (!$user->is_suspended) {
                $user->update(['is_suspended' => true, 'suspended_at' => now()]);
            }
            return back()->withErrors(['error' => 'Your borrowing privileges are disabled due to overdue items. Please return them first.']);
        }

        try {
            $places = $request->places_of_event ?? [];
            if ($request->resource_id && !in_array($request->resource_id, $places)) {
                array_unshift($places, $request->resource_id);
            }

            // If there's extra facility data, pack it into remarks
            $remarks = null;
            if ($request->has('form_data')) {
                $remarks = json_encode($request->form_data);
            } elseif ($request->has('equipments_needed') || $request->has('other_place_of_event')) {
                $remarks = json_encode([
                    'other_place' => $request->other_place_of_event,
                    'equipments_needed' => $request->equipments_needed,
                    'other_equipments' => $request->other_equipments
                ]);
            }

            if (empty($places)) {
                // If NO places selected but they typed "other", we might just map it to a generic facility if we had one.
                // But for now, throw error or require a resource_id
                if (!$request->resource_id) {
                    throw new \Exception("Please select at least one Place of Event.");
                }
                $places = [$request->resource_id];
            }

            $lastReservation = null;
            foreach ($places as $placeId) {
                $lastReservation = $this->transactionService->requestEquipment(
                    auth()->id(),
                    $placeId,
                    (int) $request->quantity,
                    $request->purpose,
                    $request->duration,
                    $remarks
                );
            }

            return back()->with([
                'success' => 'Reservation requested successfully.',
                'new_reservation_id' => $lastReservation ? $lastReservation->id : null
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function approve(Request $request, $id)
    {
        try {
            $this->transactionService->approveTransaction($id);
            return back()->with('success', 'Transaction approved.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function signDocument(Request $request, $id)
    {
        $request->validate(['role' => 'required|string', 'signature' => 'required|string', 'printed_name' => 'nullable|string']);
        
        try {
            $reservation = \App\Models\Reservation::findOrFail($id);
            $remarks = json_decode($reservation->remarks, true) ?? [];
            if (!isset($remarks['signatures'])) {
                $remarks['signatures'] = [];
            }
            if ($request->has('printed_name')) {
                $remarks['signatures'][$request->role] = [
                    'data' => $request->signature,
                    'name' => $request->printed_name
                ];
            } else {
                $remarks['signatures'][$request->role] = $request->signature;
            }
            $reservation->remarks = json_encode($remarks);
            
            // Check if all signatures are present to auto-approve
            if (isset($remarks['signatures']['fmo']) && 
                isset($remarks['signatures']['ocm']) && 
                isset($remarks['signatures']['osa']) && 
                isset($remarks['signatures']['oss'])) {
                $reservation->status = 'ACTIVE';
                $reservation->approved_at = now();
            }
            
            $reservation->save();
            return back()->with('success', 'Document signed successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function printForm($id)
    {
        $reservation = \App\Models\Reservation::with(['user', 'resource'])->findOrFail($id);
        return inertia('PrintFacilityForm', [
            'reservation' => $reservation
        ]);
    }

    public function decline(Request $request, $id)
    {
        $request->validate([
            'remarks' => 'required|string|max:500'
        ]);

        try {
            $this->transactionService->declineTransaction($id, $request->remarks);
            return back()->with('success', 'Transaction declined.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function returnEquipment(Request $request, $id)
    {
        $request->validate([
            'condition' => 'required|string|max:255',
            'remarks' => 'nullable|string|max:1000',
            'photo' => 'nullable|image|max:5120'
        ]);

        try {
            $conditionText = $request->condition;
            if ($request->condition === 'Damaged' || $request->condition === 'Bad') {
                if ($request->remarks) {
                    $conditionText .= ' - Remarks: ' . $request->remarks;
                }
                if ($request->hasFile('photo')) {
                    $path = $request->file('photo')->store('returns', 'public');
                    $conditionText .= ' - Photo: ' . $path;
                }
            }

            $this->transactionService->returnTransaction($id, $conditionText);
            return back()->with('success', 'Equipment returned successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function verifyScan(Request $request)
    {
        $request->validate([
            'qr_pass_code' => 'required|string',
            // optionally pass face_scan_data for backend verification later
        ]);

        try {
            $reservation = $this->transactionService->verifyAndRelease($request->qr_pass_code);
            return back()->with('success', 'Equipment released successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
    public function rejectRelease(Request $request, $id)
    {
        $request->validate([
            'remarks' => 'required|string|max:500'
        ]);

        try {
            $this->transactionService->rejectRelease($id, $request->remarks);
            return back()->with('success', 'Release rejected.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $reservation = \App\Models\Reservation::findOrFail($id);
            // Ensure the user owns this reservation or is an admin
            if ($reservation->user_id !== auth()->id() && auth()->user()->role !== 'admin') {
                throw new \Exception("Unauthorized to delete this reservation.");
            }
            if (in_array($reservation->status, ['ACTIVE', 'BORROWED', 'APPROVED_AWAITING_PICKUP', 'DISPENSED'])) {
                throw new \Exception("Active or currently borrowed reservations cannot be deleted.");
            }

            $reservation->delete();
            return back()->with('success', 'Reservation deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'purpose' => 'required|string|max:255'
        ]);

        try {
            $reservation = \App\Models\Reservation::findOrFail($id);
            if ($reservation->user_id !== auth()->id() && auth()->user()->role !== 'admin') {
                throw new \Exception("Unauthorized to edit this reservation.");
            }
            if (!in_array($reservation->status, ['PENDING_APPROVAL', 'DECLINED', 'REJECTED'])) {
                throw new \Exception("Only pending or declined reservations can be edited.");
            }
            $reservation->purpose = $request->purpose;
            
            // Also update inside remarks if it exists (for Facility Reservations)
            if ($reservation->remarks) {
                $remarks = is_string($reservation->remarks) ? json_decode($reservation->remarks, true) : $reservation->remarks;
                if (is_array($remarks) && isset($remarks['name_of_activity'])) {
                    $remarks['name_of_activity'] = $request->purpose;
                    $reservation->remarks = json_encode($remarks);
                }
            }

            $reservation->save();
            return back()->with('success', 'Reservation updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
