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
        $isAdmin = $this->user()->isAdmin();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique((new User)->getTable())->ignore($this->user())],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'taxpayer_name' => [$isAdmin ? 'prohibited' : 'required', 'nullable', 'string', 'max:255'],
            'taxpayer_type' => [$isAdmin ? 'prohibited' : 'required', 'nullable', Rule::in(['legal', 'individual'])],
            'national_id' => [$isAdmin ? 'prohibited' : 'required', 'nullable', 'digits_between:10,14'],
            'economic_code' => [$isAdmin ? 'prohibited' : 'required', 'nullable', 'regex:/^(?:\d{11}|\d{14})$/'],
            'fiscal_id' => [
                $isAdmin ? 'prohibited' : 'required',
                'nullable',
                'regex:/^[A-Z0-9]{6}$/',
                Rule::unique((new TaxpayerProfile)->getTable())->ignore($taxpayerProfile),
            ],
            'branch_code' => ['nullable', 'digits_between:1,4'],
            'private_key' => [
                $isAdmin ? 'prohibited' : Rule::requiredIf($taxpayerProfile === null),
                'nullable',
                'file',
                'extensions:pem,key,txt',
                'max:64',
                new ValidPrivateKey,
            ],
        ];
    }
}
