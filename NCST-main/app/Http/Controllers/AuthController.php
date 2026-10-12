<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Handle an incoming registration request.
     */
    public function register(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'role' => 'required|in:student,staff,faculty',
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users', 'regex:/@ncst\.edu\.ph$/'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'face_descriptor' => 'nullable|array',
        ];

        if ($request->role === 'student') {
            $rules['school_id'] = ['required', 'string', 'max:255', 'unique:users', 'regex:/^\d{4}-\d{5}$/'];
        } else {
            $rules['school_id'] = ['required', 'string', 'max:255', 'unique:users'];
        }

        $request->validate($rules);

        $user = User::create([
            'name' => $request->name,
            'role' => $request->role,
            'school_id' => $request->school_id,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'face_descriptor' => $request->face_descriptor,
        ]);

        // Log the user in automatically after registration
        Auth::login($user);

        // Return back so the frontend can trigger its animation, 
        // after which the frontend will use Inertia to visit the dashboard
        return back();
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Return back so the frontend can trigger its animation, 
            // after which the frontend will use Inertia to visit /dashboard
            return back();
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Destroy an authenticated session.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('success', 'Logged out successfully.');
    }
}
