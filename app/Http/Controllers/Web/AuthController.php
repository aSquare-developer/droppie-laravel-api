<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $user = User::findByCredentials($credentials['email'], $credentials['password']);

        if (! $user) {
            return back()
                ->withErrors(['email' => 'Invalid credentials.'])
                ->onlyInput('email');
        }

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()
            ->intended(route('dashboard'))
            ->with('success', 'You are signed in to Droppie.');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(RegisterUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $user->profile()->create([
                'first_name' => $validated['name'],
            ]);

            $user->vehicles()->create([
                'is_active' => true,
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Your Droppie account is ready.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('success', 'You have been signed out.');
    }
}
