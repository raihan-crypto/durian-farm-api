<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BlockRequest extends FormRequest
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
            'village_id' => ['required', 'uuid', 'exists:villages,id'],
            'name' => ['required', 'string', 'max:100'],
            'area_hectares' => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],
            'description' => ['nullable', 'string'],
        ];
    }
}
