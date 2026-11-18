<?php

namespace App\Http\Requests\Api\V1;

use App\Services\SyncEngineService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncPullRequest extends FormRequest
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
            'since' => ['nullable', 'date'],
            'entities' => ['nullable', 'array'],
            'entities.*' => ['string', Rule::in(SyncEngineService::PULLABLE_ENTITIES)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }
}
