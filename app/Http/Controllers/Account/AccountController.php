<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateEmailRequest;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * The logged-in user's account page (name, email, password, logout).
     */
    public function show(): Response
    {
        return Inertia::render('Account', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your details have been saved.')]);

        return to_route('account.show');
    }

    public function updateEmail(UpdateEmailRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->email === $request->validated('email')) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('That is already your email address.')]);

            return to_route('account.show');
        }

        $user->forceFill([
            'email' => $request->validated('email'),
            'email_verified_at' => null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your email address has been updated.')]);

        return to_route('account.show');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your password has been changed.')]);

        return to_route('account.show');
    }
}
