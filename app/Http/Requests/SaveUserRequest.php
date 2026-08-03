<?php

namespace App\Http\Requests;

use App\Enums\Plan;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'permissions' => $this->input('permissions', []),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique((new User)->getTable())->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'plan' => ['required', Rule::enum(Plan::class)],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(['customers', 'goods', 'invoices'])],
            'license_expires_at' => ['nullable', 'date'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
