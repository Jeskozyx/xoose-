<?php

namespace App\Http\Controllers;

use App\Jobs\BuildApplicationJob;
use App\Models\AppBuild;
use App\Models\AppProject;
use App\Services\BuildService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class AppBuildController extends Controller
{
    public function __construct(protected BuildService $buildService) {}

    /**
     * Request a new build for the given project.
     */
    public function store(Request $request, AppProject $project)
    {
        $format = $request->input('format', 'onedir');
        if (!in_array($format, ['onedir', 'onefile'])) {
            $format = 'onedir';
        }

        $build = $this->buildService->createBuild($project, $format);

        // Check if queue driver is sync or if user requested direct build
        $isSync = config('queue.default') === 'sync' || $request->boolean('sync');

        if ($isSync) {
            $this->buildService->executeBuild($build);
        } else {
            BuildApplicationJob::dispatch($build);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Proses build telah dimulai.',
                'build_id' => $build->id,
                'status' => $build->fresh()->status,
            ]);
        }

        return redirect()
            ->route('builder.projects.show', $project)
            ->with('success', "Build #{$build->id} ({$format}) sedang diproses!");
    }

    /**
     * Get JSON status and sanitized log excerpt for real-time polling.
     */
    public function status(AppBuild $build)
    {
        $logContent = '';
        if ($build->log_path && File::exists($build->log_path)) {
            $lines = file($build->log_path, FILE_IGNORE_NEW_LINES);
            $totalLines = count($lines);
            // Return last 60 lines for smooth log viewing
            $recentLines = array_slice($lines, max(0, $totalLines - 60));
            $logContent = implode("\n", $recentLines);
        }

        return response()->json([
            'id' => $build->id,
            'status' => $build->status,
            'format' => $build->format,
            'started_at' => $build->started_at?->toIso8601String(),
            'finished_at' => $build->finished_at?->toIso8601String(),
            'exit_code' => $build->exit_code,
            'error_message' => $build->error_message,
            'has_artifact' => !empty($build->artifact_path) && File::exists($build->artifact_path),
            'log' => $logContent,
        ]);
    }

    /**
     * Securely download build executable or zipped bundle.
     */
    public function download(AppBuild $build)
    {
        if ($build->status !== 'succeeded' || empty($build->artifact_path)) {
            abort(404, 'Artefak build belum siap atau proses build belum berhasil.');
        }

        $baseBuildsDir = realpath(config('application-builder.paths.builds', 'D:/KULIAH/KODINGAN/xoose-loveyou/builds'));
        $artifactPath = realpath($build->artifact_path);

        // Security check: Path containment
        if (!$artifactPath || !$baseBuildsDir || !str_starts_with($artifactPath, $baseBuildsDir)) {
            abort(403, 'Akses ke artefak ditolak karena pelanggaran batasan direktori.');
        }

        if (!File::exists($artifactPath)) {
            abort(404, 'File executable tidak ditemukan di server.');
        }

        // For onedir mode: create or serve a zip archive containing the entire distribution folder
        if ($build->format === 'onedir') {
            $distDir = dirname($artifactPath);
            $zipPath = dirname($distDir) . '/' . $build->project->slug . '_build_' . $build->id . '.zip';

            if (!File::exists($zipPath)) {
                $zip = new ZipArchive();
                if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                    $files = File::allFiles($distDir);
                    foreach ($files as $file) {
                        $relativePath = substr($file->getPathname(), strlen($distDir) + 1);
                        $zip->addFile($file->getPathname(), $relativePath);
                    }
                    $zip->close();
                }
            }

            if (File::exists($zipPath)) {
                return response()->download($zipPath, $build->project->slug . '-windows.zip');
            }
        }

        // Single file exe download
        return response()->download($artifactPath, basename($artifactPath));
    }
}
