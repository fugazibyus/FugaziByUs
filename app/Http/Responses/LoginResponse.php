<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome back!')]);

        return redirect()->intended(Fortify::redirects('login'));
    }
}
