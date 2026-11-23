<?php

namespace App\Http\Controllers;

use App\Models\AppProject;
use App\Models\AppTemplate;
use App\Services\TemplateManifestService;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AppProjectController extends Controller
{
    public function __construct(
        protected TemplateManifestService $manifestService,
        protected WorkspaceService $workspaceService
    ) {}

    /**
     * Display a listing of projects and available templates.
     */
    public function index()
    {
        $this->manifestService->ensureDefaultTemplateRegistered();

        $templates = AppTemplate::where('is_active', true)->get();
        $projects = AppProject::with(['template', 'latestBuild', 'latestRevision'])
            ->orderByDesc('id')
            ->get();

        return view('builder.index', compact('projects', 'templates'));
    }

    /**
     * Show the form for creating a new project.
     */
    public function create(Request $request)
    {
        $defaultTemplate = $this->manifestService->ensureDefaultTemplateRegistered();
        $templateId = $request->query('template_id', $defaultTemplate->id);
        $template = AppTemplate::findOrFail($templateId);

        $schema = $this->manifestService->getFormSchema();
        $existingSlugs = AppProject::pluck('slug')->all();
        $suggestedSlug = $this->generateUniqueSlug('loveyou-app', $existingSlugs);

        return view('builder.create', compact('template', 'schema', 'suggestedSlug', 'existingSlugs'));
    }

    /**
     * Generate a unique project slug that is not yet used in app_projects.
     */
    protected function generateUniqueSlug(string $base = 'loveyou-app', ?array $existingSlugs = null): string
    {
        $taken = $existingSlugs ?? AppProject::pluck('slug')->all();
        $slug = $base;
        $counter = 2;

        while (in_array($slug, $taken, true)) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Store a newly created project in database and initialize workspace.
     */
    public function store(Request $request)
    {
        $defaults = $this->manifestService->getDefaultValues();
        $schema = $this->manifestService->getFormSchema();

        // Process feature checkboxes explicitly so unchecked items become false
        $rawFeatures = $request->input('features', []);
        $features = [];
        foreach ($schema['features'] as $feat) {
            $key = $feat['key'];
            $features[$key] = !empty($rawFeatures[$key]);
        }

        $input = $request->all();
        $input['features'] = $features;
        $input['configuration'] = array_merge($defaults['configuration'], $request->input('configuration', []));

        // Handle icon choice
        $iconPreset = $request->input('icon_preset', 'heart');
        $iconFile = 'heart.ico';
        if ($iconPreset === 'chrome') {
            $iconFile = 'chrome.ico';
        }

        $input['configuration']['icon'] = $iconFile;

        $validated = $this->manifestService->validateInput($input, uniqueSlug: true);
        $template = $this->manifestService->ensureDefaultTemplateRegistered();

        // 1. Create project
        $project = AppProject::create([
            'app_template_id' => $template->id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'status' => 'draft',
            'features' => $validated['features'] ?? [],
            'configuration' => $validated['configuration'] ?? [],
        ]);

        // 2. Create revision 1
        $project->revisions()->create([
            'revision_number' => 1,
            'features' => $project->features,
            'configuration' => $project->configuration,
            'change_summary' => 'Inisialisasi proyek aplikasi pertama kali',
        ]);

        // 3. Handle custom icon upload if present
        if ($request->hasFile('custom_icon') && $request->file('custom_icon')->isValid()) {
            $uploadedIcon = $request->file('custom_icon');
            $customName = $project->slug . '-custom.ico';
            $destDir = config('application-builder.paths.workspace') . '/' . $project->slug . '/backend/resources';
            \Illuminate\Support\Facades\File::makeDirectory($destDir, 0755, true, true);
            $uploadedIcon->move($destDir, $customName);

            $config = $project->configuration;
            $config['icon'] = $customName;
            $project->update(['configuration' => $config]);
        }

        // 4. Initialize workspace
        try {
            $this->workspaceService->syncWorkspace($project, force: true);
        } catch (\Throwable $e) {
            $project->update(['status' => 'failed']);
            return redirect()
                ->route('builder.projects.show', $project)
                ->with('error', 'Proyek dibuat tetapi inisialisasi workspace gagal: ' . $e->getMessage());
        }

        return redirect()
            ->route('builder.projects.show', $project)
            ->with('success', "Proyek '{$project->name}' berhasil dibuat dan workspace telah disiapkan!");
    }

    /**
     * Display the specified project.
     */
    public function show(AppProject $project)
    {
        $project->load(['template', 'revisions', 'builds']);
        $schema = $this->manifestService->getFormSchema();

        return view('builder.show', compact('project', 'schema'));
    }

    /**
     * Show the form for editing the project configuration.
     */
    public function edit(AppProject $project)
    {
        $schema = $this->manifestService->getFormSchema();

        return view('builder.edit', compact('project', 'schema'));
    }

    /**
     * Update project configuration, record a new revision, and sync workspace.
     */
    public function update(Request $request, AppProject $project)
    {
        $schema = $this->manifestService->getFormSchema();

        // Process feature checkboxes explicitly so unchecked items become false
        $rawFeatures = $request->input('features', []);
        $features = [];
        foreach ($schema['features'] as $feat) {
            $key = $feat['key'];
            $features[$key] = !empty($rawFeatures[$key]);
        }

        // Don't change slug once project is created
        $data = $request->all();
        $data['slug'] = $project->slug;
        $data['features'] = $features;

        // Handle icon choice (preserve existing if not explicitly changed)
        $iconPreset = $request->input('icon_preset');
        $iconFile = $project->configuration['icon'] ?? 'heart.ico';
        if ($iconPreset === 'chrome') {
            $iconFile = 'chrome.ico';
        } elseif ($iconPreset === 'heart') {
            $iconFile = 'heart.ico';
        }

        // Handle custom icon upload if present on edit
        if ($request->hasFile('custom_icon') && $request->file('custom_icon')->isValid()) {
            $uploadedIcon = $request->file('custom_icon');
            $customName = $project->slug . '-custom.ico';
            $destDir = config('application-builder.paths.workspace') . '/' . $project->slug . '/backend/resources';
            \Illuminate\Support\Facades\File::makeDirectory($destDir, 0755, true, true);
            $uploadedIcon->move($destDir, $customName);
            $iconFile = $customName;
        }

        $configData = $request->input('configuration', []);
        $configData['icon'] = $iconFile;
        $data['configuration'] = $configData;

        $validated = $this->manifestService->validateInput($data);
        $validated['configuration']['icon'] = $iconFile;

        $nextRevNumber = ($project->revisions()->max('revision_number') ?? 0) + 1;

        // Record revision
        $project->revisions()->create([
            'revision_number' => $nextRevNumber,
            'features' => $validated['features'] ?? [],
            'configuration' => $validated['configuration'] ?? [],
            'change_summary' => $request->input('change_summary', "Update revisi #{$nextRevNumber}"),
        ]);

        $project->update([
            'name' => $validated['name'],
            'features' => $validated['features'] ?? [],
            'configuration' => $validated['configuration'] ?? [],
        ]);

        // Resync workspace
        try {
            $this->workspaceService->syncWorkspace($project, force: true);
        } catch (\Throwable $e) {
            return redirect()
                ->route('builder.projects.show', $project)
                ->with('error', 'Konfigurasi disimpan tetapi sinkronisasi workspace gagal: ' . $e->getMessage());
        }

        return redirect()
            ->route('builder.projects.show', $project)
            ->with('success', "Revisi #{$nextRevNumber} berhasil disimpan dan workspace telah disinkronkan!");
    }

    /**
     * Remove the specified project.
     */
    public function destroy(AppProject $project)
    {
        $name = $project->name;
        $project->delete();

        return redirect()
            ->route('builder.projects.index')
            ->with('success', "Proyek '{$name}' telah dihapus.");
    }
}
