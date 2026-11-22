<?php

namespace App\Http\Controllers;

use App\Models\AppProject;
use App\Services\TemplateManifestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class AppEditorController extends Controller
{
    public function __construct(protected TemplateManifestService $manifestService) {}

    /**
     * Get list of editable files and content of selected file.
     */
    public function show(AppProject $project, Request $request)
    {
        $schema = $this->manifestService->getFormSchema();
        $allowlist = $schema['editable_files'] ?? [];

        $selectedFile = $request->query('file', $allowlist[0] ?? 'backend/config.ini');

        // Security check: Must be in allowlist
        if (!in_array($selectedFile, $allowlist, true)) {
            abort(403, 'File ini tidak diizinkan untuk diedit.');
        }

        $workspaceRoot = realpath(config('application-builder.paths.workspace', 'D:/KULIAH/KODINGAN/xoose-loveyou/workspace'));
        $targetFile = realpath(rtrim($project->workspace_path, '/\\') . '/' . $selectedFile);

        if (!$targetFile || !str_starts_with($targetFile, $workspaceRoot)) {
            abort(403, 'Akses file ditolak karena berada di luar batasan direktori workspace.');
        }

        $content = File::exists($targetFile) ? File::get($targetFile) : '';

        return view('builder.editor', compact('project', 'allowlist', 'selectedFile', 'content'));
    }

    /**
     * Save updated content to the allowlisted file with backup.
     */
    public function save(AppProject $project, Request $request)
    {
        $schema = $this->manifestService->getFormSchema();
        $allowlist = $schema['editable_files'] ?? [];

        $selectedFile = $request->input('file');
        $content = $request->input('content', '');

        if (!in_array($selectedFile, $allowlist, true)) {
            abort(403, 'File ini tidak diizinkan untuk diedit.');
        }

        // Limit maximum editable file size to 2MB to prevent memory exhaustion
        if (strlen($content) > 2 * 1024 * 1024) {
            return back()->with('error', 'Ukuran konten melebihi batas maksimum 2MB.');
        }

        $workspaceRoot = realpath(config('application-builder.paths.workspace', 'D:/KULIAH/KODINGAN/xoose-loveyou/workspace'));
        $targetFile = realpath(rtrim($project->workspace_path, '/\\') . '/' . $selectedFile);

        if (!$targetFile || !str_starts_with($targetFile, $workspaceRoot)) {
            abort(403, 'Akses file ditolak karena berada di luar batasan direktori workspace.');
        }

        // 1. Create timestamped backup inside workspace/.backups
        $backupDir = dirname($targetFile) . '/.backups';
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }
        $backupName = basename($targetFile) . '.' . time() . '.bak';
        File::copy($targetFile, $backupDir . '/' . $backupName);

        // 2. Save new content
        File::put($targetFile, $content);

        // 3. Increment revision number
        $nextRev = ($project->revisions()->max('revision_number') ?? 0) + 1;
        $project->revisions()->create([
            'revision_number' => $nextRev,
            'features' => $project->features,
            'configuration' => $project->configuration,
            'change_summary' => "Edit manual file: {$selectedFile}",
        ]);

        return back()->with('success', "Perubahan pada '{$selectedFile}' berhasil disimpan (Revisi #{$nextRev})!");
    }
}
