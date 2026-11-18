<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PhotoUploadRequest extends FormRequest
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
            'photo.required' => 'File foto wajib diunggah.',
            'photo.image' => 'File harus berupa gambar.',
            'photo.mimes' => 'Format foto harus JPEG, PNG, atau WebP.',
            'photo.max' => 'Ukuran foto melebihi batas yang diizinkan.',
        ];
    }
}
