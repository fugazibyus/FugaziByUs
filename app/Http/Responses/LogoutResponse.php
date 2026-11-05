<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Fortify;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'info', 'message' => __('You have been logged out.')]);

        return redirect(Fortify::redirects('logout', '/'));
    }
}
