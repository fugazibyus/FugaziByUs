<?php

namespace App\Http\Requests\Account;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->nameRules();
    }

    protected function prepareForValidation(): void
    {
        // Treat blank inputs as "not set".
        $this->merge([
            'first_name' => $this->filled('first_name') ? trim((string) $this->input('first_name')) : null,
            'last_name' => $this->filled('last_name') ? trim((string) $this->input('last_name')) : null,
        ]);
    }
}
