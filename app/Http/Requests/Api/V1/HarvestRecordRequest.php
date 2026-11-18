<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HarvestRecordRequest extends FormRequest
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
        $maxKilobytes = (int) config('durian.uploads.max_kilobytes', 5120);
        $mimes = implode(',', (array) config('durian.uploads.mimes', ['jpeg', 'jpg', 'png', 'webp']));

        return [
            'session_id' => ['required', 'uuid', 'exists:harvest_sessions,id'],
            'tree_id' => ['required', 'uuid', 'exists:trees,id'],
            'fruit_count' => ['nullable', 'integer', 'min:0', 'required_without:details'],
            'total_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:999999999.999', 'required_without:details'],
            'notes' => ['nullable', 'string'],
            'details' => ['nullable', 'array'],
            'details.*.fruit_grade' => ['nullable', 'string', 'max:50'],
            'details.*.fruit_count' => ['required_with:details', 'integer', 'min:0'],
            'details.*.weight_kg' => ['required_with:details', 'numeric', 'min:0', 'max:999999999.999'],
            'details.*.notes' => ['nullable', 'string'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', "mimes:{$mimes}", "max:{$maxKilobytes}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fruit_count.min' => 'Jumlah buah tidak boleh negatif.',
            'total_weight_kg.min' => 'Total berat panen tidak boleh negatif.',
            'details.*.fruit_count.min' => 'Jumlah buah pada detail grade tidak boleh negatif.',
            'details.*.weight_kg.min' => 'Berat buah pada detail grade tidak boleh negatif.',
        ];
    }
}
