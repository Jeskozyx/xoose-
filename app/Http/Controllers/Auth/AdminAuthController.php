<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Remote\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('remote.apps.index');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'Email atau kata sandi admin Xoose tidak valid.']);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        if ($user) {
            $this->auditLog->adminAction(
                actor: $user,
                message: "Admin '{$user->email}' masuk ke Xoose Remote Service Manager.",
                serviceKey: 'auth',
                context: ['ip' => $request->ip()],
            );
        }

        return redirect()->intended(route('remote.apps.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user) {
            $this->auditLog->adminAction(
                actor: $user,
                message: "Admin '{$user->email}' keluar dari Xoose Remote Service Manager.",
                serviceKey: 'auth',
                context: ['ip' => $request->ip()],
            );
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda berhasil keluar dari sesi admin.');
    }
}
