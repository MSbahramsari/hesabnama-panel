<?php

namespace App\Http\Requests;

use App\Enums\Plan;
use App\Enums\UserRole;
use App\Models\TaxpayerProfile;
use App\Models\User;
use App\Rules\ValidPrivateKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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
            'fiscal_id' => Str::upper(trim((string) $this->input('fiscal_id'))),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $user = $this->route('user');
        $taxpayerProfile = $user?->taxpayerProfile;
        $requiresTaxpayerProfile = $this->requiresTaxpayerProfile($taxpayerProfile);

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
            'taxpayer_name' => [Rule::requiredIf($requiresTaxpayerProfile), 'nullable', 'string', 'max:255'],
            'taxpayer_type' => [Rule::requiredIf($requiresTaxpayerProfile), 'nullable', Rule::in(['legal', 'individual'])],
            'national_id' => [Rule::requiredIf($requiresTaxpayerProfile), 'nullable', 'digits_between:10,14'],
            'economic_code' => [Rule::requiredIf($requiresTaxpayerProfile), 'nullable', 'digits_between:10,14'],
            'fiscal_id' => [
                Rule::requiredIf($requiresTaxpayerProfile),
                'nullable',
                'regex:/^[A-Z0-9]{6}$/',
                Rule::unique((new TaxpayerProfile)->getTable())->ignore($taxpayerProfile),
            ],
            'branch_code' => ['nullable', 'digits_between:1,10'],
            'private_key' => [
                Rule::requiredIf($requiresTaxpayerProfile && $taxpayerProfile === null),
                'nullable',
                'file',
                'extensions:pem,key,txt',
                'max:64',
                new ValidPrivateKey,
            ],
        ];
    }

    private function requiresTaxpayerProfile(?TaxpayerProfile $taxpayerProfile): bool
    {
        if ($this->input('role') !== UserRole::Admin->value || $taxpayerProfile !== null || $this->hasFile('private_key')) {
            return true;
        }

        foreach (['taxpayer_name', 'national_id', 'economic_code', 'fiscal_id'] as $field) {
            if (filled($this->input($field))) {
                return true;
            }
        }

        return false;
    }
}
