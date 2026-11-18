<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FertilizationRecordRequest extends FormRequest
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
        $methods = array_keys((array) config('durian.activities.fertilization_methods', []));

        return [
            'tree_id' => ['required', 'uuid', 'exists:trees,id'],
            'activity_date' => ['nullable', 'date', 'before_or_equal:now'],
            'method' => ['sometimes', 'required', Rule::in($methods)],
            'dosage_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:agricultural_products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.999'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.notes' => ['nullable', 'string'],
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
            'items.required' => 'Minimal satu produk pemupukan wajib diisi.',
            'items.min' => 'Minimal satu produk pemupukan wajib diisi.',
            'items.*.quantity.gt' => 'Jumlah produk harus lebih besar dari nol.',
            'activity_date.before_or_equal' => 'Tanggal pemupukan tidak boleh melebihi waktu saat ini.',
        ];
    }
}
