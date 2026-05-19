<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VendorApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'business_name' => 'required|string|max:255|unique:vendors,business_name',
            'registration_number' => 'nullable|string|max:100|unique:vendors,registration_number',
            'support_email' => 'nullable|email|max:255',
            'support_phone' => 'nullable|string|max:50',

            'aba_merchant_id' => 'nullable|string|max:255',
            'aba_api_key' => 'nullable|string|max:255',
            'bakong_account_id' => 'nullable|string|max:255',
            'bakong_merchant_name' => 'nullable|string|max:255',
            'wing_account_number' => 'nullable|string|max:255',
            'preferred_payout_method' => 'required|in:aba,bakong,wing',
            'business_license' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:5120',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ];
    }
}
