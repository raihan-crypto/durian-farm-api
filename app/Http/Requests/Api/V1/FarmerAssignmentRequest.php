<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FarmerAssignmentRequest extends FormRequest
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
            'assignment_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:assignment_date'],
        ];

        if ($this->isMethod('post')) {
            $rules['farmer_id'] = ['required', 'uuid', 'exists:farmers,id'];
        }

        return $rules;
    }
}
