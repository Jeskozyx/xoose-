<?php

namespace App\Http\Middleware;

use App\Models\AppDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDeviceAgent
{
    /**
     * Memverifikasi Bearer Token unik per perangkat (revocable) yang dikirim LoveYou Agent.
     * Menolak token kosong, tidak cocok, atau milik perangkat yang sudah dicabut (401 Unauthorized).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rawToken = $request->bearerToken();
        if (! is_string($rawToken) || trim($rawToken) === '') {
            return response()->json([
                'message' => 'Device Bearer token wajib disertakan.',
            ], 401);
        }

        $tokenHash = hash('sha256', trim($rawToken));

        /** @var AppDevice|null $device */
        $device = AppDevice::with('managedApp')
            ->where('token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->first();

        if (! $device || $device->isRevoked()) {
            return response()->json([
                'message' => 'Device token tidak valid atau akses perangkat telah dicabut.',
            ], 401);
        }

        if ($device->managedApp && ! $device->managedApp->is_active) {
            return response()->json([
                'message' => 'Aplikasi untuk perangkat ini sedang dinonaktifkan.',
            ], 403);
        }

        $request->attributes->set('agent_device', $device);

        return $next($request);
    }
}
