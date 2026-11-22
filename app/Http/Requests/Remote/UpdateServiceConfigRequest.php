<?php

namespace App\Http\Requests\Remote;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'device_id' => ['nullable', 'integer', 'exists:app_devices,id'],
            'config_json' => ['required', 'array'],
            'change_notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
