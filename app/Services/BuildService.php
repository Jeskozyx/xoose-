<?php

namespace App\Services;

use App\Models\AppBuild;
use App\Models\AppProject;
use App\Models\DeviceEnrollment;
use App\Services\Remote\DeviceEnrollmentService;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BuildService
{
    protected string $pythonBinary;
    protected string $workspaceRoot;
    protected string $outputDir;
    protected string $logsDir;
    protected string $tempDir;
    protected int $timeout;
    protected TemplateManifestService $manifestService;

    public function __construct(TemplateManifestService $manifestService)
    {
        $this->manifestService = $manifestService;
        $this->pythonBinary = config('application-builder.python', 'python');
        $this->workspaceRoot = config('application-builder.paths.workspace', 'D:/KULIAH/KODINGAN/xoose-loveyou/workspace');
        $this->outputDir = config('application-builder.paths.builds', 'D:/KULIAH/KODINGAN/xoose-loveyou/builds');
        $this->logsDir = config('application-builder.paths.logs', 'D:/KULIAH/KODINGAN/xoose-loveyou/logs');
        $this->tempDir = config('application-builder.paths.temp', 'D:/KULIAH/KODINGAN/xoose-loveyou/temp');
        $this->timeout = (int) config('application-builder.build_timeout', 3600);
    }

    /**
     * Create an AppBuild record in queued status.
     */
    public function createBuild(AppProject $project, string $format = 'onedir'): AppBuild
    {
        $revision = $project->latestRevision;

        $build = AppBuild::create([
            'app_project_id' => $project->id,
            'app_revision_id' => $revision?->id,
            'status' => 'queued',
            'format' => in_array($format, ['onedir', 'onefile']) ? $format : 'onedir',
            'engine_version' => '0.1.0',
        ]);

        $project->update(['status' => 'building']);

        return $build;
    }

    /**
     * Run the build process synchronously.
     */
    public function executeBuild(AppBuild $build): AppBuild
    {
        $project = $build->project;
        $build->update([
            'status' => 'building',
            'started_at' => now(),
        ]);

        $icon = $project->configuration['icon'] ?? 'heart.ico';
        $manifestData = $this->manifestService->generateProjectManifest(
            $project->name,
            $project->slug,
            $project->features ?: [],
            $project->configuration ?: [],
            $icon
        );

        // Override build format with the one chosen for this build
        $manifestData['build']['format'] = $build->format;

        // Integrasi Milestone 6: Jika proyek terhubung ke ManagedApp, suntikkan identitas Agent
        // (hanya enrollment_code terbatas, TIDAK PERNAH menyematkan service_role atau shared device_token)
        $managedApp = $project->managedApp;
        if ($managedApp) {
            $enrollment = $managedApp->enrollments()
                ->where('status', DeviceEnrollment::STATUS_PENDING)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->latest()
                ->first();

            if (! $enrollment || ! $enrollment->isUsable()) {
                $enrollment = app(DeviceEnrollmentService::class)->createEnrollmentCode(
                    app: $managedApp,
                    actor: null,
                    label: "Auto Build #{$build->id}",
                    maxUses: 10,
                    expiresInHours: 168,
                );
            }

            $manifestData['application']['version'] = $managedApp->current_version ?: '1.0.0';
            $manifestData['agent'] = [
                'enabled' => true,
                'api_base_url' => rtrim((string) config('app.url', 'http://localhost:8000'), '/'),
                'project_slug' => $managedApp->slug,
                'enrollment_code' => $enrollment->enrollment_code,
                'heartbeat_interval_seconds' => 30,
                'poll_interval_seconds' => 10,
                'config_sync_interval_seconds' => 60,
                'realtime_enabled' => false,
                'app_version' => $managedApp->current_version ?: '1.0.0',
            ];
            $managedApp->update(['build_status' => 'building']);
        }

        if (!File::exists($this->tempDir)) {
            File::makeDirectory($this->tempDir, 0755, true);
        }

        $tempManifestFile = rtrim($this->tempDir, '/\\') . '/build_manifest_' . $project->slug . '_' . $build->id . '.json';
        File::put($tempManifestFile, json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $args = [
            $this->pythonBinary,
            '-m',
            'xoose_builder.cli',
            'build',
            '--manifest',
            $tempManifestFile,
            '--workspace-root',
            $this->workspaceRoot,
            '--output-dir',
            $this->outputDir,
            '--logs-dir',
            $this->logsDir,
            '--build-id',
            (string) $build->id,
            '--format',
            $build->format,
        ];

        $userProfile = getenv('USERPROFILE') ?: (getenv('HOME') ?: 'C:\\Users\\Jeskozyx');
        $homeDrive = getenv('HOMEDRIVE') ?: 'C:';
        $homePath = getenv('HOMEPATH') ?: '\\Users\\Jeskozyx';
        $systemPath = getenv('PATH') ?: (getenv('Path') ?: ($_SERVER['Path'] ?? ($_SERVER['PATH'] ?? '')));
        if (empty($systemPath)) {
            $systemPath = 'C:\\WINDOWS\\system32;C:\\WINDOWS;C:\\WINDOWS\\System32\\Wbem;C:\\WINDOWS\\System32\\WindowsPowerShell\\v1.0\\;C:\\Users\\Jeskozyx\\AppData\\Local\\Programs\\Python\\Python314\\Scripts\\;C:\\Users\\Jeskozyx\\AppData\\Local\\Programs\\Python\\Python314\\';
        }

        $env = array_merge($_SERVER, $_ENV, [
            'PATH' => $systemPath,
            'Path' => $systemPath,
            'USERPROFILE' => $userProfile,
            'HOMEDRIVE' => $homeDrive,
            'HOMEPATH' => $homePath,
            'APPDATA' => getenv('APPDATA') ?: ($userProfile . '\\AppData\\Roaming'),
            'LOCALAPPDATA' => getenv('LOCALAPPDATA') ?: ($userProfile . '\\AppData\\Local'),
            'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
            'WINDIR' => getenv('WINDIR') ?: 'C:\\Windows',
            'TEMP' => getenv('TEMP') ?: sys_get_temp_dir(),
            'TMP' => getenv('TMP') ?: sys_get_temp_dir(),
        ]);

        $process = new Process($args, null, $env);
        $process->setTimeout($this->timeout);
        $process->run();

        if (File::exists($tempManifestFile)) {
            File::delete($tempManifestFile);
        }

        $stdout = trim($process->getOutput());
        $stderr = trim($process->getErrorOutput());
        $result = json_decode($stdout, true);

        $exitCode = $process->getExitCode();
        $isSuccessful = $process->isSuccessful() && !empty($result['success']);

        $expectedLog = rtrim($this->logsDir, '/\\') . "/{$project->slug}_build_{$build->id}.log";
        $logPath = File::exists($expectedLog) ? $expectedLog : null;

        if ($isSuccessful) {
            $artifactPath = $result['artifact_path'] ?? null;
            $build->update([
                'status' => 'succeeded',
                'finished_at' => now(),
                'exit_code' => $exitCode,
                'artifact_path' => $artifactPath,
                'log_path' => $logPath,
            ]);

            $project->update(['status' => 'built']);
            if ($managedApp) {
                $managedApp->update(['build_status' => 'built']);
            }
        } else {
            $errorMsg = $result['error'] ?? ($stderr ?: "Build failed with exit code {$exitCode}");
            $build->update([
                'status' => 'failed',
                'finished_at' => now(),
                'exit_code' => $exitCode,
                'log_path' => $logPath,
                'error_message' => $errorMsg,
            ]);

            $project->update(['status' => 'failed']);
            if ($managedApp) {
                $managedApp->update(['build_status' => 'failed']);
            }
        }

        return $build;
    }
}
