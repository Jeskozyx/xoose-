<?php

namespace App\Services\Remote;

use App\Models\AppBuild;
use App\Models\AppRelease;
use App\Models\ManagedApp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class ReleaseIntegrityService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function signChecksum(string $sha256Checksum): string
    {
        $key = (string) config('remote-control.release_signing_key', config('app.key'));

        return hash_hmac('sha256', strtolower(trim($sha256Checksum)), $key);
    }

    public function verifySignature(string $sha256Checksum, string $signature): bool
    {
        $expected = $this->signChecksum($sha256Checksum);

        return hash_equals($expected, strtolower(trim($signature)));
    }

    public function createRelease(ManagedApp $app, array $data, ?User $actor = null): AppRelease
    {
        $version = trim((string) $data['version']);
        $checksum = strtolower(trim((string) $data['sha256_checksum']));

        if (! preg_match('/^[a-f0-9]{64}$/', $checksum)) {
            throw ValidationException::withMessages([
                'sha256_checksum' => ['Checksum SHA-256 harus berupa 64 karakter heksadesimal valid.'],
            ]);
        }

        if (AppRelease::where('managed_app_id', $app->id)->where('version', $version)->exists()) {
            throw ValidationException::withMessages([
                'version' => ["Versi release '{$version}' sudah ada untuk aplikasi '{$app->name}'."],
            ]);
        }

        $signature = ! empty($data['signature'])
            ? strtolower(trim((string) $data['signature']))
            : $this->signChecksum($checksum);

        $activate = (bool) ($data['is_active'] ?? true);

        return DB::transaction(function () use ($app, $data, $version, $checksum, $signature, $activate, $actor) {
            if ($activate) {
                AppRelease::where('managed_app_id', $app->id)->update(['is_active' => false]);
            }

            $release = AppRelease::create([
                'managed_app_id' => $app->id,
                'version' => $version,
                'artifact_path' => (string) $data['artifact_path'],
                'artifact_url' => $data['artifact_url'] ?? null,
                'sha256_checksum' => $checksum,
                'signature' => $signature,
                'release_notes' => $data['release_notes'] ?? null,
                'is_active' => $activate,
                'published_by' => $actor?->id,
                'published_at' => $activate ? now() : null,
            ]);

            if ($activate) {
                $app->update(['current_version' => $version]);
            }

            if ($actor) {
                $this->auditLog->adminAction(
                    actor: $actor,
                    message: "Admin menerbitkan release v{$version} untuk aplikasi '{$app->name}' (SHA-256 terverifikasi).",
                    serviceKey: 'release',
                    app: $app,
                    context: [
                        'release_id' => $release->id,
                        'version' => $version,
                        'sha256_checksum' => $checksum,
                        'is_active' => $activate,
                    ],
                );
            }

            return $release;
        });
    }

    /**
     * Menerbitkan AppRelease bertanda tangan HMAC-SHA256 langsung dari hasil build EXE yang sukses.
     */
    public function publishFromBuild(
        ManagedApp $app,
        AppBuild $build,
        string $version,
        ?User $actor = null,
        ?string $releaseNotes = null
    ): AppRelease {
        if ($build->status !== 'succeeded' || empty($build->artifact_path) || ! File::isFile($build->artifact_path)) {
            throw ValidationException::withMessages([
                'build_id' => ['Build belum selesai atau file artefak EXE tidak ditemukan pada disk.'],
            ]);
        }

        $checksum = strtolower((string) hash_file('sha256', $build->artifact_path));

        return $this->createRelease($app, [
            'version' => $version,
            'artifact_path' => $build->artifact_path,
            'sha256_checksum' => $checksum,
            'release_notes' => $releaseNotes ?: "Auto-published dari Build #{$build->id} ({$build->format})",
            'is_active' => true,
        ], $actor);
    }
}
