<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\AdminRbac;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request, AuditLogService $audit): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $throttleKey = 'login:'.$request->ip().':'.$credentials['email'];

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ])->onlyInput('email');
        }

        $user = User::where('email', $credentials['email'])->first();

        if ($user && ! AdminRbac::userIsActive($user)) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors(['email' => 'This account has been deactivated.'])->onlyInput('email');
        }

        if ($user && $user->isLockedOut()) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors([
                'email' => 'Account temporarily locked. Try again after '.$user->locked_until->format('H:i').'.',
            ])->onlyInput('email');
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            RateLimiter::clear($throttleKey);

            /** @var User $authenticated */
            $authenticated = auth()->user();
            $authenticated->recordSuccessfulLogin();

            if ($authenticated->canAccessAdmin()) {
                $audit->log('auth.login', $authenticated, ['area' => 'admin']);

                return redirect()->intended(route('admin.dashboard'));
            }

            return redirect()->intended(route('account.dashboard'));
        }

        RateLimiter::hit($throttleKey, 60);
        $user?->recordFailedLogin();

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }

    public function logout(Request $request, AuditLogService $audit): RedirectResponse
    {
        if ($user = auth()->user()) {
            if ($user->canAccessAdmin()) {
                $audit->log('auth.logout', $user, ['area' => 'admin']);
            }
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
