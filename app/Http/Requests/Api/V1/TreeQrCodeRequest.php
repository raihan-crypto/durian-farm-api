<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TreeQrCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['sometimes', 'boolean'],
            'public_fields' => ['sometimes', 'nullable', 'array'],
            'public_fields.*' => ['string', 'distinct', Rule::in((array) config('durian.public_tree.available_fields', []))],
        ];
    }
}
