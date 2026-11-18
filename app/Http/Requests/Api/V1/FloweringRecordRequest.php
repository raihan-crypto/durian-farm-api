<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FloweringRecordRequest extends FormRequest
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
        $statuses = array_keys((array) config('durian.activities.flowering_statuses', []));

        return [
            'tree_id' => ['required', 'uuid', 'exists:trees,id'],
            'flowering_date' => ['nullable', 'date', 'before_or_equal:today'],
            'flowering_status' => ['required', Rule::in($statuses)],
            'flower_count' => ['nullable', 'integer', 'min:0'],
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
            'flowering_date.before_or_equal' => 'Tanggal pengamatan pembungaan tidak boleh melebihi hari ini.',
        ];
    }
}
