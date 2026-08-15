<?php

namespace App\Http\Requests;

use App\Models\TaxpayerProfile;
use App\Models\User;
use App\Rules\ValidPrivateKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fiscal_id' => Str::upper(trim((string) $this->input('fiscal_id'))),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $taxpayerProfile = $this->user()->taxpayerProfile;
        $requiresTaxpayerProfile = $this->requiresTaxpayerProfile($taxpayerProfile);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique((new User)->getTable())->ignore($this->user())],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
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
        if (! $this->user()->isAdmin() || $taxpayerProfile !== null || $this->hasFile('private_key')) {
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
