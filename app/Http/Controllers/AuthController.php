<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }

        return back()
            ->withErrors(['email' => 'These credentials do not match our records.'])
            ->onlyInput('email');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        Auth::login($user);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('landing');
    }

    // ── Google OAuth ──────────────────────────────────────────────────

    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->scopes(['https://www.googleapis.com/auth/gmail.send'])
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    public function handleGoogleCallback()
    {
        $googleUser = Socialite::driver('google')->user();

        // Find by google_id first (returning user), then fall back to email
        // so existing email/password accounts get linked automatically.
        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            $user->update([
                'google_id' => $googleUser->getId(),
                'avatar'    => $googleUser->getAvatar(),
                // Preserve existing token if Google doesn't send a new one
                // (only issued on first consent or when prompt=consent forces it)
                'google_refresh_token' => $googleUser->refreshToken ?? $user->getRawOriginal('google_refresh_token'),
            ]);
        } else {
            $user = User::create([
                'name'                 => $googleUser->getName(),
                'email'                => $googleUser->getEmail(),
                'google_id'            => $googleUser->getId(),
                'avatar'               => $googleUser->getAvatar(),
                'google_refresh_token' => $googleUser->refreshToken,
            ]);
        }

        Auth::login($user);
        request()->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
