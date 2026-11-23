<?php

namespace App\Http\Requests\Remote;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaptureRulesRequest extends FormRequest
{
    public const DOMAIN_REGEX = '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/';
    public const RULE_ID_REGEX = '/^[a-z0-9][a-z0-9_-]{1,63}$/';
    public const PROCESS_REGEX = '/^[a-zA-Z0-9._-]{1,120}$/';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $updates = [];

        if ($this->has('domain') && is_string($this->input('domain'))) {
            $updates['domain'] = strtolower(rtrim(trim($this->input('domain')), '.'));
        }
        if ($this->has('id') && is_string($this->input('id'))) {
            $trimmedId = strtolower(trim($this->input('id')));
            $updates['id'] = $trimmedId !== '' ? $trimmedId : null;
        }
        if ($this->has('process') && is_string($this->input('process'))) {
            $updates['process'] = trim($this->input('process'));
        }
        if ($this->has('window_title_contains') && is_string($this->input('window_title_contains'))) {
            $updates['window_title_contains'] = trim($this->input('window_title_contains'));
        }

        if (! empty($updates)) {
            $this->merge($updates);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $routeName = (string) ($this->route()?->getName() ?? '');

        if ($routeName === 'remote.capture-rules.policy') {
            return [
                'scope' => ['required', 'string', 'max:40'],
                'active_source' => ['required', 'string', Rule::in(['disabled', 'browser_domain', 'application_window', 'desktop_mss'])],
                'allow_live_switch' => ['nullable', 'boolean'],
                'browser_enabled' => ['nullable', 'boolean'],
                'window_enabled' => ['nullable', 'boolean'],
                'permission_mode' => ['nullable', 'string', Rule::in(['local_start', 'automatic_domain'])],
                'image_format' => ['nullable', 'string', Rule::in(['webp', 'png'])],
                'fallback_format' => ['nullable', 'string', Rule::in(['png', 'jpeg'])],
                'capture_interval_seconds' => ['nullable', 'integer', 'min:2', 'max:3600'],
                'window_interval_seconds' => ['nullable', 'integer', 'min:2', 'max:3600'],
                'local_session_required' => ['nullable', 'boolean'],
                'pause_when_browser_unfocused' => ['nullable', 'boolean'],
                'pause_when_tab_inactive' => ['nullable', 'boolean'],
                'change_notes' => ['nullable', 'string', 'max:255'],
            ];
        }

        $ruleType = (string) $this->input('rule_type', 'browser');
        $isUpdate = $routeName === 'remote.capture-rules.rules.update';

        if ($ruleType === 'window') {
            return [
                'scope' => ['required', 'string', 'max:40'],
                'rule_type' => ['required', Rule::in(['window'])],
                'id' => ['nullable', 'string', 'min:2', 'max:64', 'regex:' . self::RULE_ID_REGEX],
                'enabled' => ['nullable', 'boolean'],
                'process' => [$isUpdate ? 'sometimes' : 'required', 'string', 'min:1', 'max:120', 'regex:' . self::PROCESS_REGEX],
                'window_title_contains' => ['nullable', 'string', 'max:200'],
            ];
        }

        return [
            'scope' => ['required', 'string', 'max:40'],
            'rule_type' => ['nullable', Rule::in(['browser'])],
            'id' => ['nullable', 'string', 'min:2', 'max:64', 'regex:' . self::RULE_ID_REGEX],
            'enabled' => ['nullable', 'boolean'],
            'domain' => [$isUpdate ? 'sometimes' : 'required', 'string', 'min:3', 'max:253', 'regex:' . self::DOMAIN_REGEX],
            'include_subdomains' => ['nullable', 'boolean'],
            'schemes' => ['nullable', 'array', 'min:1', 'max:2'],
            'schemes.*' => ['string', Rule::in(['https', 'http'])],
            'browsers' => ['nullable', 'array', 'min:1', 'max:3'],
            'browsers.*' => ['string', Rule::in(['chrome', 'edge', 'firefox'])],
            'capture_interval_seconds' => ['nullable', 'integer', 'min:2', 'max:3600'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'domain.regex' => 'Domain harus berupa nama domain valid huruf kecil tanpa skema (https://), wildcard (*), port, atau path (contoh: konstantaedu.com).',
            'id.regex' => 'ID rule hanya boleh huruf kecil, angka, tanda hubung (-), atau underscore (_) minimal 2 karakter.',
            'process.regex' => 'Nama proses hanya boleh nama executable aman (contoh: chrome.exe) tanpa path direktori.',
            'active_source.in' => 'Sumber capture harus salah satu dari: disabled, browser_domain, application_window, desktop_mss.',
        ];
    }
}
