<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\LoginRateLimiter;

class AdminLoginController extends Controller
{
    public function store(
        AdminLoginRequest $request,
        LoginRateLimiter $limiter
    ) {
        $user = User::where('email', $request->email)
            ->where('admin_status', true)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            $limiter->increment($request);

            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        Auth::login($user);

        $request->session()->regenerate();
        $limiter->clear($request);

        return redirect('/admin/attendance/list');
    }

    public function destroy(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    }
}