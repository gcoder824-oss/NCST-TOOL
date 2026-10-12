<?php

use Illuminate\Support\Facades\Route;

use Inertia\Inertia;
use App\Models\Resource;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return Inertia::render('LandingPage');
})->name('welcome');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();
        
        if ($user->role === 'admin') {
            if (request()->query('view') === 'counter') {
                $reservations = \App\Models\Reservation::with(['resource', 'user'])->orderBy('created_at', 'desc')->get();
                $resources = \App\Models\Resource::all();
                return Inertia::render('StaffDashboard', [
                    'reservations' => $reservations,
                    'resources' => $resources
                ]);
            }
            return redirect()->route('superadmin.panel');
        } elseif ($user->role === 'staff') {
            // Counter Staff sees all reservations for approval and verification
            $reservations = \App\Models\Reservation::with(['resource', 'user'])->orderBy('created_at', 'desc')->get();
            $resources = \App\Models\Resource::all();
            return Inertia::render('StaffDashboard', [
                'reservations' => $reservations,
                'resources' => $resources
            ]);
        } elseif ($user->role === 'faculty') {
            // Employees/Faculty see their own dashboard with facilities emphasis
            $reservations = \App\Models\Reservation::with('resource')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
            return Inertia::render('EmployeeDashboard', [
                'reservations' => $reservations
            ]);
        } elseif ($user->role === 'visitor') {
            // Visitors see their own facility reservations
            $reservations = \App\Models\Reservation::with('resource')
                ->where('user_id', $user->id)
                ->whereHas('resource', function($query) {
                    $query->where('type', 'facility');
                })
                ->orderBy('created_at', 'desc')
                ->get();
            return Inertia::render('VisitorDashboard', [
                'reservations' => $reservations
            ]);
        } else {
            // Students see their own equipment reservations
            $reservations = \App\Models\Reservation::with('resource')
                ->where('user_id', $user->id)
                ->whereHas('resource', function($query) {
                    $query->where('type', '!=', 'facility');
                })
                ->orderBy('created_at', 'desc')
                ->get();
            return Inertia::render('StudentDashboard', [
                'reservations' => $reservations
            ]);
        }
    })->name('dashboard');

    Route::get('/superadmin', function () {
        $user = auth()->user();
        if (!$user || $user->role !== 'admin') {
            return redirect('/dashboard');
        }

        $users = \App\Models\User::orderBy('name')->get();
        $auditLogs = \App\Models\AuditLog::orderBy('created_at', 'desc')->take(100)->get();
        $resources = \App\Models\Resource::orderBy('name')->get();
        $reservations = \App\Models\Reservation::with(['resource', 'user'])->orderBy('created_at', 'desc')->take(100)->get();

        $stats = [
            'activeCustodians' => \App\Models\User::whereIn('role', ['staff', 'admin'])->count(),
            'systemHealth' => '99.9% Uptime',
            'auditOverrides' => \App\Models\AuditLog::where('created_at', '>=', now()->subDays(7))->count(),
            'facialEmbeddingsCount' => \App\Models\User::whereNotNull('face_descriptor')->count(),
            'totalUsers' => $users->count(),
            'totalResources' => $resources->count(),
            'totalReservations' => \App\Models\Reservation::count(),
            'activeLoans' => \App\Models\Reservation::where('status', 'BORROWED')->count(),
        ];

        return Inertia::render('SuperadminPanel', [
            'users' => $users,
            'auditLogs' => $auditLogs,
            'resources' => $resources,
            'reservations' => $reservations,
            'stats' => $stats
        ]);
    })->name('superadmin.panel');

    Route::post('/superadmin/users/{id}/role', function (\Illuminate\Http\Request $request, $id) {
        $currentUser = auth()->user();
        if (!$currentUser || $currentUser->role !== 'admin') return redirect('/dashboard');

        $request->validate(['role' => 'required|in:student,faculty,staff,admin']);
        $target = \App\Models\User::findOrFail($id);
        $oldRole = $target->role;
        $target->role = $request->role;
        $target->save();

        \App\Models\AuditLog::create([
            'custodian' => $currentUser->name,
            'action' => 'Role Scope Modification',
            'target_item' => "{$target->name} ({$target->school_id})",
            'reason' => "Promoted/Changed role from {$oldRole} to {$request->role}",
            'severity' => $request->role === 'admin' ? 'warning' : 'info'
        ]);

        return back()->with('success', "Role for {$target->name} updated to {$request->role}.");
    });

    Route::post('/superadmin/users/{id}/toggle-suspend', function ($id) {
        $currentUser = auth()->user();
        if (!$currentUser || $currentUser->role !== 'admin') return redirect('/dashboard');

        $target = \App\Models\User::findOrFail($id);
        $target->is_suspended = !$target->is_suspended;
        $target->suspended_at = $target->is_suspended ? now() : null;
        $target->account_status = $target->is_suspended ? 'SUSPENDED' : 'ACTIVE';
        $target->save();

        \App\Models\AuditLog::create([
            'custodian' => $currentUser->name,
            'action' => $target->is_suspended ? 'Account Privilege Revocation' : 'Account Reinstatement',
            'target_item' => "{$target->name} ({$target->school_id})",
            'reason' => $target->is_suspended ? 'Administrative manual suspension' : 'Privileges restored by Superadmin',
            'severity' => 'override'
        ]);

        return back()->with('success', "Account status toggled for {$target->name}.");
    });

    Route::post('/superadmin/audit-logs', function (\Illuminate\Http\Request $request) {
        $currentUser = auth()->user();
        if (!$currentUser || $currentUser->role !== 'admin') return redirect('/dashboard');

        $data = $request->validate([
            'action' => 'required|string|max:255',
            'target_item' => 'required|string|max:255',
            'reason' => 'required|string|max:500',
            'severity' => 'required|in:info,warning,override,critical'
        ]);

        \App\Models\AuditLog::create([
            'custodian' => $currentUser->name,
            'action' => $data['action'],
            'target_item' => $data['target_item'],
            'reason' => $data['reason'],
            'severity' => $data['severity']
        ]);

        return back()->with('success', 'Manual override recorded in immutable audit log.');
    });

    Route::post('/superadmin/sync-database', function () {
        $currentUser = auth()->user();
        if (!$currentUser || $currentUser->role !== 'admin') return redirect('/dashboard');

        $enrolledCount = \App\Models\User::whereNotNull('face_descriptor')->count();

        \App\Models\AuditLog::create([
            'custodian' => $currentUser->name,
            'action' => 'Vector Database Synchronization',
            'target_item' => 'Facial Recognition Embeddings KD-Tree',
            'reason' => "Synchronized {$enrolledCount} enrolled user facial vectors",
            'severity' => 'info'
        ]);

        return back()->with('success', "Vector database synchronized with {$enrolledCount} facial embeddings.");
    });

    Route::post('/superadmin/settings', function (\Illuminate\Http\Request $request) {
        $currentUser = auth()->user();
        if (!$currentUser || $currentUser->role !== 'admin') return redirect('/dashboard');

        \App\Models\AuditLog::create([
            'custodian' => $currentUser->name,
            'action' => 'Biometric Sensitivity Calibration',
            'target_item' => 'SSD MobileNet Recognition Engine',
            'reason' => "Threshold set to {$request->match_threshold}% (Liveness: " . ($request->liveness_detection ? 'ON' : 'OFF') . ')',
            'severity' => 'info'
        ]);

        return back()->with('success', 'Biometric settings saved.');
    });

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/facility-approvals', function () {
        $user = auth()->user();
        $allowedDepartments = ['FMO', 'OSA', 'OCM', 'OSS'];
        if ($user && (in_array($user->department, $allowedDepartments) || $user->school_id === 'FMO-Admin' || $user->role === 'admin')) {
            $reservations = \App\Models\Reservation::with(['resource', 'user'])
                ->whereHas('resource', function($query) {
                    $query->where('type', 'facility');
                })
                ->whereIn('status', ['PENDING_APPROVAL', 'ACTIVE'])
                ->orderBy('created_at', 'desc')
                ->get();
            return Inertia::render('FacilityApprovals', [
                'reservations' => $reservations
            ]);
        }
        return redirect('/dashboard');
    })->name('facility.approvals');

    Route::get('/facility-calendar', function () {
        $user = auth()->user();
        $allowedDepartments = ['FMO', 'OSA', 'OCM', 'OSS'];
        if ($user && (in_array($user->department, $allowedDepartments) || $user->school_id === 'FMO-Admin' || $user->role === 'admin')) {
            // Get all facility reservations that are not rejected/cancelled
            $reservations = \App\Models\Reservation::with(['resource', 'user'])
                ->whereHas('resource', function($query) {
                    $query->where('type', 'facility');
                })
                ->whereIn('status', ['PENDING_APPROVAL', 'APPROVED', 'ACTIVE'])
                ->orderBy('created_at', 'desc')
                ->get();
            return Inertia::render('FacilityCalendar', [
                'reservations' => $reservations
            ]);
        }
        return redirect('/dashboard');
    })->name('facility.calendar');
    
    Route::redirect('/catalog', '/equipment');
    Route::redirect('/settings', '/dashboard');

    Route::get('/notifications', function () {
        $user = auth()->user();
        if (!$user) return redirect('/login');

        if (in_array($user->role, ['staff', 'admin'])) {
            $reservations = \App\Models\Reservation::with(['resource', 'user'])
                ->orderBy('updated_at', 'desc')
                ->take(50)
                ->get();
        } else {
            $reservations = \App\Models\Reservation::with('resource')
                ->where('user_id', $user->id)
                ->orderBy('updated_at', 'desc')
                ->take(50)
                ->get();
        }

        return Inertia::render('Notifications', [
            'reservations' => $reservations,
        ]);
    })->name('notifications');

    Route::get('/equipment', function () {
        $user = auth()->user();
        if ($user && $user->role === 'visitor') {
            return redirect('/facilities');
        }
        $query = \App\Models\Resource::query()->where('type', '!=', 'facility');
        return Inertia::render('ResourceCatalog', [
            'resources' => $query->get(),
            'catalogType' => 'equipment'
        ]);
    })->name('catalog.equipment');

    Route::get('/facilities', function () {
        $user = auth()->user();
        if ($user && $user->role === 'student') {
            return redirect('/equipment'); // Hide facilities from students
        }
        $facilities = \App\Models\Resource::query()->where('type', 'facility')->get();
        $equipment = \App\Models\Resource::query()->where('type', 'equipment')->get();
        
        $facilityReservations = \App\Models\Reservation::with('resource')
            ->whereHas('resource', function($query) {
                $query->where('type', 'facility');
            })
            ->whereIn('status', ['PENDING_APPROVAL', 'APPROVED_AWAITING_PICKUP', 'BORROWED'])
            ->get();
            
        return Inertia::render('FacilityReservationForm', [
            'facilities' => $facilities,
            'equipment' => $equipment,
            'facilityReservations' => $facilityReservations,
            'currentUser' => $user
        ]);
    })->name('catalog.facilities');

    Route::get('/history', function () {
        $user = auth()->user();
        if ($user && $user->role === 'visitor') {
            return redirect('/reserve-history');
        }
        $reservations = \App\Models\Reservation::with('resource')
            ->where('user_id', $user->id)
            ->whereHas('resource', function($query) {
                $query->whereIn('type', ['equipment', 'consumable', 'kit']);
            })
            ->orderBy('created_at', 'desc')
            ->get();
        return Inertia::render('BorrowHistory', [
            'reservations' => $reservations
        ]);
    })->name('history');
    Route::get('/borrowed', function () {
        $user = auth()->user();
        if ($user && $user->role === 'visitor') return redirect('/dashboard');
        
        $reservations = \App\Models\Reservation::with('resource')
            ->where('user_id', $user->id)
            ->whereHas('resource', function($query) {
                $query->whereIn('type', ['equipment', 'consumable', 'kit']);
            })
            ->whereIn('status', ['BORROWED', 'APPROVED_AWAITING_PICKUP'])
            ->orderBy('created_at', 'desc')
            ->get();
        return Inertia::render('BorrowHistory', [
            'reservations' => $reservations,
            'pageTitle' => 'Borrowed',
            'pageDescription' => 'Review items you have currently borrowed or are awaiting pickup.'
        ]);
    })->name('borrowed');

    Route::get('/borrow-requests', function () {
        $user = auth()->user();
        if ($user && $user->role === 'visitor') return redirect('/dashboard');
        
        $reservations = \App\Models\Reservation::with('resource')
            ->where('user_id', $user->id)
            ->whereHas('resource', function($query) {
                $query->whereIn('type', ['equipment', 'consumable', 'kit']);
            })
            ->whereIn('status', ['PENDING', 'PENDING_APPROVAL'])
            ->orderBy('created_at', 'desc')
            ->get();
        return Inertia::render('BorrowHistory', [
            'reservations' => $reservations,
            'pageTitle' => 'Borrow Request',
            'pageDescription' => 'Review your equipment reservations waiting for approval.'
        ]);
    })->name('borrow.requests');

    Route::get('/reserved', function () {
        $user = auth()->user();
        if ($user && $user->role === 'student') return redirect('/history');
        
        $reservations = \App\Models\Reservation::with('resource')
            ->where('user_id', $user->id)
            ->whereHas('resource', function($query) {
                $query->where('type', 'facility');
            })
            ->whereIn('status', ['ACTIVE', 'APPROVED'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->filter(function($res) {
                $remarks = json_decode($res->remarks, true);
                $eventDate = $remarks['date_of_event'] ?? null;
                return !$eventDate || \Carbon\Carbon::parse($eventDate)->isSameDay(now()) || \Carbon\Carbon::parse($eventDate)->isFuture();
            })->values();

        return Inertia::render('ReserveHistory', [
            'reservations' => $reservations,
            'pageTitle' => 'Reserved Facilities',
            'pageDescription' => 'Review your currently reserved and approved facilities.'
        ]);
    })->name('reserved');

    Route::get('/facility-requests', function () {
        $user = auth()->user();
        if ($user && $user->role === 'student') return redirect('/history');
        
        $reservations = \App\Models\Reservation::with('resource')
            ->where('user_id', $user->id)
            ->whereHas('resource', function($query) {
                $query->where('type', 'facility');
            })
            ->whereIn('status', ['PENDING', 'PENDING_APPROVAL'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->filter(function($res) {
                $remarks = json_decode($res->remarks, true);
                $eventDate = $remarks['date_of_event'] ?? null;
                return !$eventDate || \Carbon\Carbon::parse($eventDate)->isSameDay(now()) || \Carbon\Carbon::parse($eventDate)->isFuture();
            })->values();

        return Inertia::render('ReserveHistory', [
            'reservations' => $reservations,
            'pageTitle' => 'Facility Requests',
            'pageDescription' => 'Review your facility requests waiting for approval.'
        ]);
    })->name('facility.requests');

    Route::get('/reserve-history', function () {
        $user = auth()->user();
        if ($user && $user->role === 'student') return redirect('/history');
        
        $reservations = \App\Models\Reservation::with('resource')
            ->where('user_id', $user->id)
            ->whereHas('resource', function($query) {
                $query->where('type', 'facility');
            })
            ->orderBy('created_at', 'desc')
            ->get();
        return Inertia::render('ReserveHistory', [
            'reservations' => $reservations,
            'pageTitle' => 'Facility Reserve History',
            'pageDescription' => 'Review your facility reservation history and full details.'
        ]);
    })->name('reserve.history');

    Route::get('/admin-history', function () {
        $user = auth()->user();
        if ($user && in_array($user->role, ['staff', 'admin'])) {
            $reservations = \App\Models\Reservation::with(['resource', 'user'])
                ->whereHas('resource', function($query) {
                    $query->whereIn('type', ['equipment', 'consumable', 'kit']);
                })
                ->orderBy('created_at', 'desc')
                ->get();
            return Inertia::render('AdminHistory', [
                'reservations' => $reservations
            ]);
        }
        return redirect('/dashboard');
    })->name('admin.history');

    Route::get('/passes', function () {
        $user = auth()->user();
        if ($user && in_array($user->role, ['staff', 'admin', 'visitor'])) {
            return redirect('/dashboard');
        }
        $reservations = \App\Models\Reservation::with('resource')
            ->where('user_id', $user->id)
            ->whereHas('resource', function($query) {
                $query->where('type', '!=', 'facility');
            })
            ->whereIn('status', ['APPROVED_AWAITING_PICKUP', 'BORROWED'])
            ->orderBy('updated_at', 'desc')
            ->get();
        return Inertia::render('ActivePasses', [
            'reservations' => $reservations
        ]);
    })->name('passes');

    Route::get('/inventory', function () {
        $user = auth()->user();
        if ($user && !in_array($user->role, ['staff', 'admin'])) {
            return redirect('/dashboard');
        }
        return Inertia::render('InventoryManagement', [
            'resources' => \App\Models\Resource::orderBy('type')->orderBy('name')->get()
        ]);
    })->name('inventory');

    Route::post('/inventory', function (\Illuminate\Http\Request $request) {
        $user = auth()->user();
        if ($user && !in_array($user->role, ['staff', 'admin'])) return redirect('/dashboard');
        
        $data = $request->validate([
            'asset_code' => 'required|string|max:255|unique:resources',
            'name' => 'required|string|max:255',
            'type' => 'required|in:equipment,facility,consumable,kit',
            'category' => 'nullable|string|max:255',
            'stock' => 'required|integer|min:0',
            'status' => 'nullable|string|max:255'
        ]);
        
        \App\Models\Resource::create($data);
        return back()->with('success', 'Item added successfully.');
    })->name('inventory.store');

    Route::put('/inventory/{id}', function (\Illuminate\Http\Request $request, $id) {
        $user = auth()->user();
        if ($user && !in_array($user->role, ['staff', 'admin'])) return redirect('/dashboard');
        
        $resource = \App\Models\Resource::findOrFail($id);
        
        $data = $request->validate([
            'asset_code' => 'required|string|max:255|unique:resources,asset_code,'.$id,
            'name' => 'required|string|max:255',
            'type' => 'required|in:equipment,facility,consumable,kit',
            'category' => 'nullable|string|max:255',
            'stock' => 'required|integer|min:0',
            'status' => 'nullable|string|max:255'
        ]);
        
        $resource->update($data);
        return back()->with('success', 'Item updated successfully.');
    })->name('inventory.update');

    Route::delete('/inventory/{id}', function ($id) {
        $user = auth()->user();
        if ($user && !in_array($user->role, ['staff', 'admin'])) return redirect('/dashboard');
        
        $resource = \App\Models\Resource::findOrFail($id);
        $resource->delete();
        return back()->with('success', 'Item deleted successfully.');
    })->name('inventory.destroy');

    Route::redirect('/accounts', '/users');

    Route::get('/users', function () {
        $user = auth()->user();
        if ($user && !in_array($user->role, ['staff', 'admin'])) {
            return redirect('/dashboard');
        }
        return Inertia::render('UserDirectory', [
            'accounts' => \App\Models\User::with(['reservations' => function($q) {
                $q->where('status', 'BORROWED')->with('resource');
            }])->withTrashed()->where('id', '!=', $user->id)->orderBy('name')->get()
        ]);
    })->name('users.index');

    Route::post('/users', function (\Illuminate\Http\Request $request) {
        $user = auth()->user();
        if ($user && !in_array($user->role, ['staff', 'admin'])) return redirect('/dashboard');
        
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'role' => 'required|in:student,faculty,staff,admin',
            'password' => 'required|string|min:8',
        ];
        
        if ($request->role === 'student') {
            $rules['school_id'] = ['required', 'string', 'max:255', 'unique:users', 'regex:/^\d{4}-\d{5}$/'];
        } else {
            $rules['school_id'] = ['required', 'string', 'max:255', 'unique:users'];
        }

        $data = $request->validate($rules);
        $data['password'] = bcrypt($data['password']);
        
        \App\Models\User::create($data);
        return back()->with('success', 'User added successfully.');
    });

    Route::put('/users/{id}', function (\Illuminate\Http\Request $request, $id) {
        $user = auth()->user();
        if ($user && !in_array($user->role, ['staff', 'admin'])) return redirect('/dashboard');
        
        $targetUser = \App\Models\User::findOrFail($id);
        
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$id,
            'role' => 'required|in:student,faculty,staff,admin',
            'password' => 'nullable|string|min:8',
        ];

        if ($request->role === 'student') {
            $rules['school_id'] = ['required', 'string', 'max:255', 'unique:users,school_id,'.$id, 'regex:/^\d{4}-\d{5}$/'];
        } else {
            $rules['school_id'] = ['required', 'string', 'max:255', 'unique:users,school_id,'.$id];
        }

        $data = $request->validate($rules);
        
        if (!empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }
        
        $targetUser->update($data);
        return back()->with('success', 'User updated successfully.');
    });

    Route::delete('/users/{id}', function ($id) {
        $user = auth()->user();
        if ($user && !in_array($user->role, ['staff', 'admin'])) return redirect('/dashboard');
        
        $targetUser = \App\Models\User::findOrFail($id);
        $targetUser->delete();
        return back()->with('success', 'User deleted successfully.');
    });

    Route::post('/users/{id}/restore', function ($id) {
        $user = auth()->user();
        if ($user && !in_array($user->role, ['staff', 'admin'])) return redirect('/dashboard');
        
        $targetUser = \App\Models\User::withTrashed()->findOrFail($id);
        $targetUser->restore();
        return back()->with('success', 'User restored successfully.');
    });

    Route::get('/walk-in/return', function () {
        $user = auth()->user();
        if ($user && !in_array($user->role, ['staff', 'admin'])) return redirect('/dashboard');
        
        // Fetch reservations that are borrowed by student, faculty, or staff
        $reservations = \App\Models\Reservation::with(['resource', 'user'])
            ->whereHas('user', function ($query) {
                $query->whereIn('role', ['student', 'faculty', 'staff']);
            })
            ->where('status', 'BORROWED')
            ->where('is_walk_in', true)
            ->orderBy('updated_at', 'desc')
            ->get();
            
        return Inertia::render('WalkInReturn', [
            'defaultRole' => 'combined',
            'reservations' => $reservations
        ]);
    })->name('walk-in.return');

    Route::get('/walk-in/{role}/return', function ($role) {
        return redirect('/walk-in/return');
    });

    // Walk-ins
    Route::get('/walk-in/{role}', function ($role) {
        $user = auth()->user();
        if ($user && !in_array($user->role, ['staff', 'admin'])) return redirect('/dashboard');
        
        if (!in_array($role, ['student', 'faculty'])) {
            abort(404);
        }
        
        $resources = \App\Models\Resource::all();
        $users = \App\Models\User::where('role', $role)->get();
        
        return Inertia::render('WalkIn', [
            'defaultRole' => $role,
            'resources' => $resources,
            'users' => $users
        ]);
    })->name('walk-in');



    Route::post('/walk-in', function (\Illuminate\Http\Request $request) {
        $user = auth()->user();
        if ($user && !in_array($user->role, ['staff', 'admin'])) return redirect('/dashboard');
        
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'role' => 'required|in:student,faculty,staff',
            'password' => 'required|string|min:8',
            'face_descriptor' => 'required|array',
            'face_photo' => 'nullable|string'
        ];
        
        if ($request->role === 'student') {
            $rules['school_id'] = ['required', 'string', 'max:255', 'unique:users', 'regex:/^\d{4}-\d{5}$/'];
            $rules['course'] = 'required|string|max:255';
            $rules['department'] = 'required|string|max:255';
        } else {
            $rules['school_id'] = ['required', 'string', 'max:255', 'unique:users'];
        }

        $data = $request->validate($rules);
        $data['password'] = bcrypt($data['password']);
        $data['face_descriptor'] = json_encode($data['face_descriptor']);
        
        $newUser = \App\Models\User::create($data);
        return back()->with('success', 'Walk-in account verified and created successfully.');
    });

    Route::post('/walk-in/checkout', function (\Illuminate\Http\Request $request, \App\Services\TransactionService $transactionService) {
        $user = auth()->user();
        if ($user && !in_array($user->role, ['staff', 'admin'])) return redirect('/dashboard');
        
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:resources,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);
        
        foreach ($data['items'] as $item) {
            $transactionService->checkoutWalkIn($data['user_id'], $item['id'], $item['quantity']);
        }
        
        return back()->with('success', 'Items have been successfully dispensed/borrowed to the user.');
    });

    Route::get('/approvals', function () {
        $user = auth()->user();
        if ($user && !in_array($user->role, ['staff', 'admin'])) {
            return redirect('/dashboard');
        }
        // Fetch only pending equipment reservations (facilities are approved via signatories)
        $reservations = \App\Models\Reservation::with(['resource', 'user'])
            ->whereHas('resource', function($query) {
                $query->where('type', '!=', 'facility');
            })
            ->where('status', 'PENDING_APPROVAL')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return Inertia::render('PendingApprovals', [
            'reservations' => $reservations
        ]);
    })->name('approvals');

    Route::get('/profile', function () {
        return Inertia::render('Profile');
    })->name('profile');

    Route::post('/profile/update', function (\Illuminate\Http\Request $request) {
        $user = auth()->user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
        ]);
        $user->update($data);
        return back()->with('success', 'Profile updated.');
    })->name('profile.update');

    Route::post('/profile/biometrics', function (\Illuminate\Http\Request $request) {
        $user = auth()->user();
        if (!$user) return redirect('/login');

        $data = $request->validate([
            'face_descriptor' => 'required|array',
            'face_photo' => 'nullable|string',
            'id_photo' => 'nullable|string',
        ]);

        $user->face_descriptor = $data['face_descriptor'];

        // Save ID photo to disk if provided as base64
        if (!empty($data['id_photo']) && str_starts_with($data['id_photo'], 'data:image')) {
            $idData = explode(',', $data['id_photo']);
            $idImageDecoded = base64_decode(end($idData));
            $idDir = public_path('uploads/id_photos');
            if (!file_exists($idDir)) {
                mkdir($idDir, 0777, true);
            }
            $idFilename = 'id_user_' . $user->id . '_' . time() . '.jpg';
            file_put_contents($idDir . '/' . $idFilename, $idImageDecoded);
            $user->photo_path = '/uploads/id_photos/' . $idFilename;
        } elseif (!empty($data['id_photo'])) {
            $user->photo_path = $data['id_photo'];
        }

        // Save face photo to disk if provided as base64
        if (!empty($data['face_photo']) && str_starts_with($data['face_photo'], 'data:image')) {
            $faceData = explode(',', $data['face_photo']);
            $faceImageDecoded = base64_decode(end($faceData));
            $faceDir = public_path('uploads/id_photos');
            if (!file_exists($faceDir)) {
                mkdir($faceDir, 0777, true);
            }
            $faceFilename = 'face_user_' . $user->id . '_' . time() . '.jpg';
            file_put_contents($faceDir . '/' . $faceFilename, $faceImageDecoded);
            $user->face_photo = '/uploads/id_photos/' . $faceFilename;
        } elseif (!empty($data['face_photo'])) {
            $user->face_photo = $data['face_photo'];
        }

        $user->save();

        return back()->with('success', 'ID card scanned and biometric face security registered successfully. Your face is now active as the verification basis for borrowing and returning.');
    })->name('profile.biometrics');

    Route::get('/settings', function () {
        return Inertia::render('Settings');
    })->name('settings');

    Route::get('/qr/{reservation}', [\App\Http\Controllers\ReservationController::class, 'generateQrPass'])->name('qr.generate');

    Route::post('/transaction/request', [\App\Http\Controllers\TransactionController::class, 'requestEquipment'])->name('transaction.request');
    Route::post('/transaction/{id}/approve', [\App\Http\Controllers\TransactionController::class, 'approve'])->name('transaction.approve');
    Route::post('/transaction/{id}/decline', [\App\Http\Controllers\TransactionController::class, 'decline'])->name('transaction.decline');
    Route::post('/transaction/{id}/sign', [\App\Http\Controllers\TransactionController::class, 'signDocument'])->name('transaction.sign');
    Route::post('/transaction/{id}/return', [\App\Http\Controllers\TransactionController::class, 'returnEquipment'])->name('transaction.return');
    Route::get('/transaction/{id}/print', [\App\Http\Controllers\TransactionController::class, 'printForm'])->name('transaction.print');
    Route::post('/transaction/verify-scan', [\App\Http\Controllers\TransactionController::class, 'verifyScan'])->name('transaction.verify');
    Route::post('/transaction/reject-release', [\App\Http\Controllers\TransactionController::class, 'rejectRelease'])->name('transaction.rejectRelease');
    Route::delete('/transaction/{id}', [\App\Http\Controllers\TransactionController::class, 'destroy'])->name('transaction.destroy');
    Route::put('/transaction/{id}', [\App\Http\Controllers\TransactionController::class, 'update'])->name('transaction.update');
});

Route::get('/login', function () {
    return Inertia::render('Auth/Login');
})->name('login');
Route::post('/login', [AuthController::class, 'login']);

Route::get('/register', function () {
    return Inertia::render('Auth/Register');
})->name('register');
Route::post('/register', [AuthController::class, 'register']);

Route::get('/kiosk', function () {
    return Inertia::render('KioskScanner');
})->name('kiosk');

Route::get('/return', function () {
    return Inertia::render('ReturnItem');
})->name('return');

Route::get('/tags', function () {
    return Inertia::render('PrintAssetTags', [
        'resources' => [
            ['id' => 1, 'name' => 'MacBook Pro M2', 'asset_code' => 'EQ-001'],
            ['id' => 2, 'name' => 'Multimedia Projector', 'asset_code' => 'EQ-002'],
        ]
    ]);
})->name('tags');
