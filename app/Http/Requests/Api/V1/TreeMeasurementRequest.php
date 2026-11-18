<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TreeMeasurementRequest extends FormRequest
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
            'tree_id' => ['required', 'uuid', 'exists:trees,id'],
            'measured_at' => ['nullable', 'date', 'before_or_equal:now'],
            'diameter_cm' => ['nullable', 'numeric', 'gt:0', 'max:999999.99', 'required_without_all:height_m,canopy_width_m'],
            'height_m' => ['nullable', 'numeric', 'gt:0', 'max:999999.99', 'required_without_all:diameter_cm,canopy_width_m'],
            'canopy_width_m' => ['nullable', 'numeric', 'gt:0', 'max:999999.99', 'required_without_all:diameter_cm,height_m'],
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
            'diameter_cm.required_without_all' => 'Minimal salah satu ukuran (diameter, tinggi, atau lebar tajuk) wajib diisi.',
            'height_m.required_without_all' => 'Minimal salah satu ukuran (diameter, tinggi, atau lebar tajuk) wajib diisi.',
            'canopy_width_m.required_without_all' => 'Minimal salah satu ukuran (diameter, tinggi, atau lebar tajuk) wajib diisi.',
            'measured_at.before_or_equal' => 'Tanggal pengukuran tidak boleh melebihi waktu saat ini.',
        ];
    }
}
