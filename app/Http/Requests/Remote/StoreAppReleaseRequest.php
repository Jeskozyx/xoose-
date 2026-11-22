<?php

namespace App\Http\Requests\Remote;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'max:32'],
            'artifact_path' => ['required', 'string', 'max:500'],
            'artifact_url' => ['nullable', 'string', 'max:1000'],
            'sha256_checksum' => ['required', 'string', 'size:64'],
            'signature' => ['nullable', 'string', 'max:128'],
            'release_notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
