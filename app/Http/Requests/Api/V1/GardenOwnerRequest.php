<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GardenOwnerRequest extends FormRequest
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
        $rules = [
            'ownership_percentage' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];

        if ($this->isMethod('post')) {
            $rules['owner_id'] = ['required', 'uuid', 'exists:owners,id'];
        }

        return $rules;
    }
}
