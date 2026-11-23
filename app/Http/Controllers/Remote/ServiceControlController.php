<?php

namespace App\Http\Controllers\Remote;

use App\Http\Controllers\Controller;
use App\Models\AppDevice;
use App\Models\ServiceDefinition;
use App\Services\Remote\CommandDispatcherService;
use App\Services\Remote\DeviceEnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceControlController extends Controller
{
    public function __construct(
        private readonly CommandDispatcherService $commandDispatcher,
        private readonly DeviceEnrollmentService $enrollmentService,
    ) {}

    public function index(Request $request, AppDevice $device): View
    {
        Gate::authorize('view', $device);
        $this->enrollmentService->ensureDefaultServiceDefinitions();

        $device->load(['managedApp', 'serviceStatuses', 'commands' => fn ($q) => $q->limit(15)]);
        $definitions = ServiceDefinition::orderBy('id')
            ->when(! config('remote-control.capture_rules_enabled', false), fn ($q) => $q->where('service_key', '!=', 'browser_capture_service'))
            ->get();
        $statusMap = $device->serviceStatuses->keyBy('service_key');

        return view('remote.services.index', [
            'device' => $device,
            'definitions' => $definitions,
            'statusMap' => $statusMap,
        ]);
    }

    /**
     * POST /remote/devices/{device}/services/{service}/command
     * Mengirim perintah tervalidasi allowlist (start/stop/pause/resume/restart) ke service tertentu.
     */
    public function dispatch(Request $request, AppDevice $device, string $service): RedirectResponse
    {
        Gate::authorize('control', $device);

        if ($service === 'browser_capture_service' && ! config('remote-control.capture_rules_enabled', false)) {
            return back()->with('error', "Layanan 'browser_capture_service' sedang dalam tahap Beta (dinonaktifkan sementara).");
        }

        $validated = $request->validate([
            'action' => ['required', 'string', 'max:32'],
            'ttl_seconds' => ['nullable', 'integer', 'min:10', 'max:3600'],
        ]);

        try {
            $command = $this->commandDispatcher->dispatch(
                device: $device,
                serviceKey: $service,
                action: (string) $validated['action'],
                payload: [],
                actor: $request->user(),
                ttlSeconds: isset($validated['ttl_seconds']) ? (int) $validated['ttl_seconds'] : null,
            );
        } catch (ValidationException $e) {
            $firstErr = collect($e->errors())->flatten()->first() ?? 'Perintah ditolak.';

            return back()->with('error', $firstErr);
        }

        return back()->with(
            'success',
            "Command '{$command->action}' untuk service '{$command->service_key}' telah dikirim (ID: {$command->command_id})."
        );
    }
}
