<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HarvestSessionRequest extends FormRequest
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
            'season_id' => ['required', 'uuid', 'exists:harvest_seasons,id'],
            'garden_id' => ['nullable', 'uuid', 'exists:gardens,id'],
            'harvest_date' => ['required', 'date'],
            'session_name' => ['nullable', 'string', 'max:150'],
            'weather_condition' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
