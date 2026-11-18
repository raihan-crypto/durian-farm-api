<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VillageRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'district' => ['nullable', 'string', 'max:150'],
            'regency' => ['nullable', 'string', 'max:150'],
            'province' => ['nullable', 'string', 'max:150'],
        ];
    }
}
