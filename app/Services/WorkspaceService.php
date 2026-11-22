<?php

namespace App\Services;

use App\Models\AppProject;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class WorkspaceService
{
    protected string $pythonBinary;
    protected string $workspaceRoot;
    protected string $tempDir;
    protected TemplateManifestService $manifestService;

    public function __construct(TemplateManifestService $manifestService)
    {
        $this->manifestService = $manifestService;
        $this->pythonBinary = config('application-builder.python', 'python');
        $this->workspaceRoot = config('application-builder.paths.workspace', 'D:/KULIAH/KODINGAN/xoose-loveyou/workspace');
        $this->tempDir = config('application-builder.paths.temp', 'D:/KULIAH/KODINGAN/xoose-loveyou/temp');
    }

    /**
     * Create or refresh project workspace via Python CLI.
     */
    public function syncWorkspace(AppProject $project, bool $force = true): string
    {
        // 1. Generate full project manifest
        $icon = $project->configuration['icon'] ?? 'heart.ico';
        $manifestData = $this->manifestService->generateProjectManifest(
            $project->name,
            $project->slug,
            $project->features ?: [],
            $project->configuration ?: [],
            $icon
        );

        if (!File::exists($this->tempDir)) {
            File::makeDirectory($this->tempDir, 0755, true);
        }

        $tempManifestFile = rtrim($this->tempDir, '/\\') . '/manifest_' . $project->slug . '_' . time() . '.json';
        File::put($tempManifestFile, json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 2. Prepare array arguments for Symfony Process
        $args = [
            $this->pythonBinary,
            '-m',
            'xoose_builder.cli',
            'workspace',
            '--manifest',
            $tempManifestFile,
            '--workspace-root',
            $this->workspaceRoot,
        ];

        if ($force) {
            $args[] = '--force';
        }

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
        $process->setTimeout(60);
        $process->run();

        // Always clean up temp manifest file
        if (File::exists($tempManifestFile)) {
            File::delete($tempManifestFile);
        }

        $stdout = trim($process->getOutput());
        $stderr = trim($process->getErrorOutput());

        $result = json_decode($stdout, true);

        if (!$process->isSuccessful() || empty($result['success'])) {
            $errorMsg = $result['error'] ?? $stderr ?: 'Gagal membuat workspace proyek.';
            throw new RuntimeException("Workspace Error ({$project->slug}): {$errorMsg}");
        }

        $workspacePath = $result['workspace_path'] ?? ($this->workspaceRoot . '/' . $project->slug);

        // Update project with workspace path and status
        $project->update([
            'workspace_path' => $workspacePath,
            'status' => 'ready',
        ]);

        return $workspacePath;
    }
}
