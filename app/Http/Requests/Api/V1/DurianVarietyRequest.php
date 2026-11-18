<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DurianVarietyRequest extends FormRequest
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
        $varietyId = $this->route('id');

        return [
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('durian_varieties', 'code')->ignore($varietyId),
            ],
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('durian_varieties', 'name')->ignore($varietyId),
            ],
            'origin' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'characteristics' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Nama varietas sudah terdaftar.',
            'code.unique' => 'Kode varietas sudah digunakan.',
        ];
    }
}
