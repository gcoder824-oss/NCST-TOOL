<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $pendingCount = 0;
        $pendingList = [];
        $overdueWarnings = [];
        $suspensionNotice = null;
        
        $user = $request->user();

        if ($user) {
            // Overdue and Suspension logic
            if ($user->role !== 'admin' && $user->role !== 'staff') {
                $borrowedItems = \App\Models\Reservation::with('resource')
                    ->where('user_id', $user->id)
                    ->where('status', 'BORROWED')
                    ->whereNotNull('due_date')
                    ->get();
                $now = \Carbon\Carbon::now();
                $shouldSuspend = false;
                $hasOverdueItems = false;

                foreach ($borrowedItems as $item) {
                    $dueDate = \Carbon\Carbon::parse($item->due_date);
                    $diffInSeconds = $now->diffInSeconds($dueDate, false);
                    
                    // diffInSeconds is negative if past due date
                    if ($diffInSeconds <= 0) {
                        $shouldSuspend = true;
                        $hasOverdueItems = true;
                    } elseif ($diffInSeconds <= 60 && $diffInSeconds > 0) {
                        $overdueWarnings[] = [
                            'id' => $item->id,
                            'name' => $item->resource?->name,
                            'seconds_left' => max(0, $diffInSeconds),
                            'due_date' => $item->due_date
                        ];
                    }
                }

                if ($shouldSuspend && !$user->is_suspended) {
                    $user->is_suspended = true;
                    $user->suspended_at = now();
                    $user->save();
                } elseif (!$hasOverdueItems && $user->is_suspended) {
                    $hasDamaged = \App\Models\Reservation::where('user_id', $user->id)
                        ->where('status', 'RETURNED')
                        ->where('remarks', 'like', '%damaged%')
                        ->exists();

                    if (!$hasDamaged) {
                        $user->is_suspended = false;
                        $user->suspended_at = null;
                        $user->save();
                    }
                }

                if ($user->is_suspended) {
                    $hasDamaged = \App\Models\Reservation::where('user_id', $user->id)
                        ->where('status', 'RETURNED')
                        ->where('remarks', 'like', '%damaged%')
                        ->exists();
                    $suspensionNotice = $hasDamaged 
                        ? "Your account is suspended due to returning a damaged item." 
                        : "Your account is suspended due to an overdue item.";
                }
            }

            if (in_array($user->role, ['staff', 'admin'])) {
                $pendingCount = \App\Models\Reservation::where('status', 'PENDING_APPROVAL')->count();
                $pendingList = \App\Models\Reservation::with(['user', 'resource'])->where('status', 'PENDING_APPROVAL')->orderBy('created_at', 'desc')->take(5)->get();
            } else {
                $pendingCount = \App\Models\Reservation::where('user_id', $user->id)->whereIn('status', ['APPROVED_AWAITING_PICKUP', 'ACTIVE'])->count();
                $pendingList = \App\Models\Reservation::with('resource')->where('user_id', $user->id)->whereIn('status', ['APPROVED_AWAITING_PICKUP', 'ACTIVE'])->orderBy('updated_at', 'desc')->take(5)->get();
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'borrowedItems' => $borrowedItems ?? [],
            'pendingApprovalsCount' => $pendingCount,
            'pendingApprovalsList' => $pendingList,
            'overdueWarnings' => $overdueWarnings,
            'suspensionNotice' => $suspensionNotice,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
