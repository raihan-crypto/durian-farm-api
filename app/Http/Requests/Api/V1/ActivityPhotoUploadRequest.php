<?php

namespace App\Http\Requests\Api\V1;

use App\Services\ActivityTargetResolver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivityPhotoUploadRequest extends FormRequest
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
            'activity_type' => ['required', 'string', Rule::in(array_keys(ActivityTargetResolver::SUPPORTED_TYPES))],
            'activity_id' => ['required', 'uuid'],
            'photo' => ['required', 'image', "mimes:{$mimes}", "max:{$maxKilobytes}"],
            'caption' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'activity_type.in' => 'Jenis aktivitas foto tidak valid.',
            'photo.required' => 'File foto wajib diunggah.',
            'photo.image' => 'File harus berupa gambar.',
            'photo.mimes' => 'Format foto harus JPEG, PNG, atau WebP.',
            'photo.max' => 'Ukuran foto melebihi batas yang diizinkan.',
        ];
    }
}
