<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Tree;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TreeRequest extends FormRequest
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
        $treeId = $this->route('id');

        return [
            'garden_id' => ['required', 'uuid', 'exists:gardens,id'],
            'variety_id' => ['nullable', 'uuid', 'exists:durian_varieties,id'],
            'tree_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('trees', 'tree_code')
                    ->where('garden_id', $this->input('garden_id'))
                    ->ignore($treeId),
            ],
            'tree_name' => ['nullable', 'string', 'max:150'],
            'planted_at' => ['nullable', 'date', 'before_or_equal:today'],
            'planting_age_months' => ['nullable', 'integer', 'min:0', 'max:2400'],
            'initial_diameter_cm' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'initial_height_m' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'initial_canopy_width_m' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['sometimes', 'required', Rule::in(array_keys(Tree::STATUS_LABELS))],
            'notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'photo_path' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tree_code.unique' => 'Kode pohon sudah digunakan pada kebun ini.',
            'planted_at.before_or_equal' => 'Tanggal tanam tidak boleh melebihi hari ini.',
        ];
    }
}
