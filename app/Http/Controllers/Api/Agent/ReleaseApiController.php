<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\AppDevice;
use App\Models\AppRelease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReleaseApiController extends Controller
{
    /**
     * GET /api/v1/agent/releases/latest
     * Mengembalikan informasi release aktif terbaru lengkap dengan SHA-256 & tanda tangan digital.
     */
    public function latest(Request $request): JsonResponse
    {
        /** @var AppDevice $device */
        $device = $request->attributes->get('agent_device');

        $currentVersion = (string) $request->query('current_version', $device->app_version ?? '0.0.0');

        /** @var AppRelease|null $latest */
        $latest = AppRelease::where('managed_app_id', $device->managed_app_id)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if (! $latest) {
            return response()->json([
                'ok' => true,
                'update_available' => false,
                'current_version' => $currentVersion,
                'release' => null,
            ]);
        }

        $updateAvailable = version_compare(ltrim($latest->version, 'vV'), ltrim($currentVersion, 'vV'), '>');

        return response()->json([
            'ok' => true,
            'update_available' => $updateAvailable,
            'current_version' => $currentVersion,
            'release' => $latest->toAgentPayload(),
        ]);
    }

    /**
     * GET /api/v1/agent/releases/{release}/download
     * Mengunduh paket artefak release terverifikasi integritas SHA-256 secara aman untuk perangkat.
     */
    public function download(Request $request, AppRelease $release): BinaryFileResponse|JsonResponse
    {
        /** @var AppDevice $device */
        $device = $request->attributes->get('agent_device');

        if ($release->managed_app_id !== $device->managed_app_id) {
            return response()->json([
                'message' => 'Release bukan milik aplikasi perangkat ini.',
            ], 403);
        }

        $artifactPath = realpath($release->artifact_path);
        $allowedRoots = array_filter([
            realpath((string) config('application-builder.paths.builds', 'D:/KULIAH/KODINGAN/xoose-loveyou/builds')),
            realpath(storage_path('app')),
        ]);

        if (! $artifactPath || ! File::isFile($artifactPath)) {
            return response()->json(['message' => 'File artefak release tidak ditemukan.'], 404);
        }

        $isContained = false;
        foreach ($allowedRoots as $root) {
            if (str_starts_with(strtolower($artifactPath), strtolower($root))) {
                $isContained = true;
                break;
            }
        }
        if (! $isContained) {
            return response()->json(['message' => 'Lokasi artefak release berada di luar direktori yang diizinkan.'], 403);
        }

        // Verifikasi ulang integritas SHA-256 langsung pada disk sebelum streaming ke perangkat
        $actualSha = strtolower((string) hash_file('sha256', $artifactPath));
        if (! hash_equals(strtolower($release->sha256_checksum), $actualSha)) {
            return response()->json([
                'message' => 'Integritas SHA-256 artefak release pada server tidak cocok (diduga termodifikasi). Unduhan dibatalkan.',
            ], 409);
        }

        return response()->download($artifactPath, basename($artifactPath), [
            'X-Release-Version' => $release->version,
            'X-Release-Sha256' => $release->sha256_checksum,
            'X-Release-Signature' => (string) ($release->signature ?? ''),
        ]);
    }
}
