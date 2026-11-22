<?php

namespace App\Http\Controllers\Remote;

use App\Http\Controllers\Controller;
use App\Models\AppDevice;
use App\Models\ServiceLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceLogController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $levelFilter = $request->query('level');
        $eventFilter = $request->query('event_type');
        $deviceFilter = $request->query('device_id');
        $search = trim((string) $request->query('q', ''));

        $logs = ServiceLog::with(['device:id,hostname,device_code', 'managedApp:id,name,slug', 'actor:id,name,email'])
            ->where(function ($q) use ($user) {
                $q->whereHas('managedApp', fn ($aq) => $aq->where('owner_id', $user->id)->orWhereNull('owner_id'))
                    ->orWhere('actor_user_id', $user->id);
            })
            ->when($levelFilter, fn ($q) => $q->where('level', $levelFilter))
            ->when($eventFilter, fn ($q) => $q->where('event_type', $eventFilter))
            ->when($deviceFilter, fn ($q) => $q->where('device_id', $deviceFilter))
            ->when($search !== '', fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('message', 'like', "%{$search}%")
                    ->orWhere('service_key', 'like', "%{$search}%");
            }))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $devices = AppDevice::whereHas('managedApp', fn ($q) => $q->where('owner_id', $user->id)->orWhereNull('owner_id'))
            ->orderBy('hostname')
            ->get();

        return view('remote.logs.index', [
            'logs' => $logs,
            'devices' => $devices,
            'filters' => [
                'level' => $levelFilter,
                'event_type' => $eventFilter,
                'device_id' => $deviceFilter,
                'q' => $search,
            ],
        ]);
    }
}
