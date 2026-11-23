<?php

namespace App\Http\Controllers\Remote;

use App\Http\Controllers\Controller;
use App\Models\AppDevice;
use App\Models\ServiceCommand;
use App\Services\Remote\CommandDispatcherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceCommandController extends Controller
{
    public function __construct(
        private readonly CommandDispatcherService $commandDispatcher,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $statusFilter = $request->query('status');
        $deviceFilter = $request->query('device_id');
        $serviceFilter = $request->query('service_key');

        $commands = ServiceCommand::with(['device.managedApp', 'issuer'])
            ->whereHas('device.managedApp', fn ($q) => $q->where('owner_id', $user->id)->orWhereNull('owner_id'))
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->when($deviceFilter, fn ($q) => $q->where('device_id', $deviceFilter))
            ->when($serviceFilter, fn ($q) => $q->where('service_key', $serviceFilter))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $devices = AppDevice::whereHas('managedApp', fn ($q) => $q->where('owner_id', $user->id)->orWhereNull('owner_id'))
            ->orderBy('hostname')
            ->get();

        return view('remote.commands.index', [
            'commands' => $commands,
            'devices' => $devices,
            'filters' => [
                'status' => $statusFilter,
                'device_id' => $deviceFilter,
                'service_key' => $serviceFilter,
            ],
        ]);
    }

    public function cancel(Request $request, ServiceCommand $command): RedirectResponse
    {
        Gate::authorize('control', $command->device);

        try {
            $this->commandDispatcher->cancel($command, actor: $request->user());
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? 'Gagal membatalkan command.';

            return back()->with('error', $msg);
        }

        return back()->with('success', "Command {$command->command_id} berhasil dibatalkan.");
    }

    public function retry(Request $request, ServiceCommand $command): RedirectResponse
    {
        Gate::authorize('control', $command->device);

        try {
            $newCmd = $this->commandDispatcher->dispatch(
                device: $command->device,
                serviceKey: $command->service_key,
                action: $command->action,
                payload: $command->payload ?? [],
                actor: $request->user(),
            );
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? 'Gagal mengirim ulang command.';

            return back()->with('error', $msg);
        }

        return back()->with('success', "Command dikirim ulang dengan ID baru: {$newCmd->command_id}.");
    }

    public function clearAll(Request $request): RedirectResponse
    {
        $user = $request->user();

        $deletedCount = ServiceCommand::whereHas(
            'device.managedApp',
            fn ($q) => $q->where('owner_id', $user->id)->orWhereNull('owner_id')
        )->delete();

        if ($deletedCount === 0) {
            return redirect()
                ->route('remote.commands.index')
                ->with('success', 'Riwayat command sudah kosong.');
        }

        return redirect()
            ->route('remote.commands.index')
            ->with('success', "{$deletedCount} riwayat remote command berhasil dibersihkan.");
    }
}
