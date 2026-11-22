<?php

namespace App\Http\Requests\Remote;

use Illuminate\Foundation\Http\FormRequest;

class AgentEnrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enrollment_code' => ['required', 'string', 'max:64'],
            'device_uuid' => ['required', 'uuid'],
            'hostname' => ['required', 'string', 'max:255'],
            'os_info' => ['nullable', 'string', 'max:255'],
            'app_slug' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'services' => ['nullable', 'array', 'max:50'],
            'services.*.service_key' => ['required_with:services', 'string', 'max:64'],
            'services.*.display_name' => ['nullable', 'string', 'max:255'],
            'services.*.supported_actions' => ['nullable', 'array'],
            'services.*.current_status' => ['nullable', 'string', 'max:24'],
            'services.*.active_config_version' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
