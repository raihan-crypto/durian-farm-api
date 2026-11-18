<?php

namespace App\Http\Requests\Api\V1;

use App\Services\SyncEngineService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncPushRequest extends FormRequest
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
            'device_identifier' => ['nullable', 'string', 'max:150'],
            'operations' => ['required', 'array', 'min:1', 'max:100'],
            'operations.*.client_transaction_id' => ['required', 'string', 'max:100', 'distinct'],
            'operations.*.entity_type' => ['required', 'string', Rule::in(SyncEngineService::PUSHABLE_ENTITIES)],
            'operations.*.entity_id' => ['required', 'uuid'],
            'operations.*.payload' => ['required', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'operations.required' => 'Daftar operasi sinkronisasi wajib diisi.',
            'operations.*.client_transaction_id.distinct' => 'Terdapat duplikasi client_transaction_id dalam satu batch.',
            'operations.*.entity_type.in' => 'Jenis entitas sinkronisasi tidak didukung.',
        ];
    }
}
