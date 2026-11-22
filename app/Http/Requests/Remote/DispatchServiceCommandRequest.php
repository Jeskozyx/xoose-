<?php

namespace App\Http\Requests\Remote;

use Illuminate\Foundation\Http\FormRequest;

class DispatchServiceCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'service_key' => ['required', 'string', 'max:64'],
            'action' => ['required', 'string', 'max:32'],
            'payload' => ['nullable', 'array'],
            'ttl_seconds' => ['nullable', 'integer', 'min:10', 'max:3600'],
        ];
    }
}
