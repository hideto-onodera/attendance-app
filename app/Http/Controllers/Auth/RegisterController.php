<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class RegisterController extends Controller
{
    public function store(
        RegisterRequest $request,
        CreatesNewUsers $creator
    ) {
        $user = $creator->create($request->validated());

        Auth::login($user);

        $request->session()->regenerate();

        return redirect('/attendance');
    }
}