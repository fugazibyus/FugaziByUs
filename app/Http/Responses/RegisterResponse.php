<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Your account is ready. Add your name below.'),
        ]);

        return redirect(Fortify::redirects('register'));
    }
}
