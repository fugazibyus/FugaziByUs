<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

trait ProfileValidationRules
{
    /**
     * Rules for the optional first / last name fields on the account page.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function nameRules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Rules for a user's email address (registration and email change).
     *
     * @return array<int, Unique|ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $ignoreUserId = null): array
    {
        return [
            'required',
            'string',
            'lowercase',
            'email:rfc,strict',
            'max:255',
            $ignoreUserId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($ignoreUserId),
        ];
    }
}
