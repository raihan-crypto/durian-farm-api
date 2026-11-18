<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FruitDevelopmentRecordRequest extends FormRequest
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
        $stages = array_keys((array) config('durian.activities.fruit_development_stages', []));

        return [
            'tree_id' => ['required', 'uuid', 'exists:trees,id'],
            'observation_date' => ['nullable', 'date', 'before_or_equal:today'],
            'fruit_count' => ['nullable', 'integer', 'min:0'],
            'development_stage' => ['required', Rule::in($stages)],
            'notes' => ['nullable', 'string'],
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
            'observation_date.before_or_equal' => 'Tanggal pengamatan perkembangan buah tidak boleh melebihi hari ini.',
        ];
    }
}
