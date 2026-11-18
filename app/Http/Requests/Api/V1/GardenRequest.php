<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GardenRequest extends FormRequest
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
            'block_id' => ['required', 'uuid', 'exists:blocks,id'],
            'name' => ['required', 'string', 'max:150'],
            'area_hectares' => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],
            'address' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string'],
        ];

        if ($this->isMethod('post')) {
            $rules['owners'] = ['nullable', 'array'];
            $rules['owners.*.owner_id'] = ['required', 'uuid', 'exists:owners,id'];
            $rules['owners.*.ownership_percentage'] = ['nullable', 'numeric', 'min:0.01', 'max:100'];
            $rules['owners.*.start_date'] = ['nullable', 'date'];
            $rules['owners.*.end_date'] = ['nullable', 'date', 'after_or_equal:owners.*.start_date'];

            $rules['farmers'] = ['nullable', 'array'];
            $rules['farmers.*.farmer_id'] = ['required', 'uuid', 'exists:farmers,id'];
            $rules['farmers.*.assignment_date'] = ['nullable', 'date'];
            $rules['farmers.*.end_date'] = ['nullable', 'date', 'after_or_equal:farmers.*.assignment_date'];
        }

        return $rules;
    }
}
