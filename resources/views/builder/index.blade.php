@extends('layouts.app')

@section('title', 'Application Builder — Xoose')

@php
    $activeMenu = 'builder';
@endphp

@section('content')
<div class="h-full flex flex-col overflow-y-auto px-8 py-8">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-gray-100">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('dashboard') }}" class="hover:text-gray-900 transition-colors">Xoose</a>
                <span>/</span>
                <span class="text-gray-800 font-medium">Application Builder</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Projects & Templates</h1>
            <p class="text-sm text-gray-500 mt-1">Buat, kelola, kustomisasi, dan build aplikasi Windows (.exe) dari template LoveYou.</p>
        </div>

        <div class="flex items-center gap-3">
            {{-- Tombol Bersihkan Resource Supabase --}}
            <button
                type="button"
                onclick="openCleanupModal()"
                class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-white border border-rose-200 text-rose-700 text-sm font-semibold hover:bg-rose-50 hover:border-rose-300 transition-colors shadow-sm cursor-pointer"
                title="Kelola dan hapus data screenshot atau user di Supabase untuk menghemat sumber daya"
            >
                <svg class="w-4 h-4 text-rose-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    <line x1="10" y1="11" x2="10" y2="17"></line>
                    <line x1="14" y1="11" x2="14" y2="17"></line>
                </svg>
                <span>Bersihkan Supabase</span>
            </button>

            <a
                href="{{ route('builder.projects.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#1A1D1F] text-white text-sm font-semibold hover:bg-black transition-colors shadow-sm"
            >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <span>Buat Proyek Baru</span>
            </a>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session('success'))
        <div class="mt-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="mt-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-rose-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    {{-- Daftar Proyek --}}
    <div class="mt-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-900">Daftar Proyek Aktif</h2>
            <span class="text-xs text-gray-400">{{ $projects->count() }} proyek terdaftar</span>
        </div>

        @if ($projects->isEmpty())
            <div class="p-12 text-center border-2 border-dashed border-gray-200 rounded-2xl bg-gray-50/50">
                <div class="w-12 h-12 mx-auto rounded-full bg-gray-100 flex items-center justify-center text-gray-400 mb-3">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    </svg>
                </div>
                <h3 class="text-sm font-semibold text-gray-900">Belum ada proyek</h3>
                <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">Mulai dengan memilih template LoveYou dan mengisi konfigurasi aplikasi pertama Anda.</p>
                <a
                    href="{{ route('builder.projects.create') }}"
                    class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#1A1D1F] text-white text-xs font-semibold hover:bg-black transition-colors"
                >
                    + Buat Proyek Pertama
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach ($projects as $project)
                    @php
                        $statusBadge = match($project->status) {
                            'built' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                            'building' => 'bg-amber-100 text-amber-800 border-amber-200 animate-pulse',
                            'ready' => 'bg-sky-100 text-sky-800 border-sky-200',
                            'failed' => 'bg-rose-100 text-rose-800 border-rose-200',
                            default => 'bg-gray-100 text-gray-700 border-gray-200',
                        };
                        $latestBuild = $project->latestBuild;
                    @endphp
                    <div class="p-5 rounded-2xl border border-gray-200 bg-white hover:shadow-md hover:border-gray-300 transition-all flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[11px] font-mono uppercase tracking-wider text-gray-400">{{ $project->template->name ?? 'Template' }}</span>
                                        @php
                                            $iconName = $project->configuration['icon'] ?? 'heart.ico';
                                            $iconEmoji = str_contains($iconName, 'chrome') ? '🌐 Chrome' : (str_contains($iconName, 'custom') ? '📁 Custom' : '❤️ Heart');
                                        @endphp
                                        <span class="px-1.5 py-0.5 text-[10px] font-medium rounded bg-gray-100 text-gray-600">{{ $iconEmoji }}</span>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-900 leading-snug">{{ $project->name }}</h3>
                                    <code class="text-xs text-gray-500">{{ $project->slug }}</code>
                                </div>
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full border {{ $statusBadge }}">
                                    {{ ucfirst($project->status) }}
                                </span>
                            </div>

                            <div class="mt-4 pt-3 border-t border-gray-100 text-xs text-gray-500 space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span>Interval Capture:</span>
                                    <span class="font-medium text-gray-700">{{ $project->configuration['interval_seconds'] ?? 3 }}s ({{ strtoupper($project->configuration['image_format'] ?? 'jpg') }})</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Windows Startup:</span>
                                    <span class="font-medium {{ !empty($project->features['auto_start']) ? 'text-emerald-600' : 'text-gray-400' }}">
                                        {{ !empty($project->features['auto_start']) ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Revisi:</span>
                                    <span class="font-medium text-gray-700">#{{ $project->revisions->first()->revision_number ?? 1 }}</span>
                                </div>
                                @if ($latestBuild)
                                    <div class="flex items-center justify-between pt-1 border-t border-gray-100">
                                        <span>Build Terakhir:</span>
                                        <span class="font-medium {{ $latestBuild->isSuccessful() ? 'text-emerald-600' : 'text-gray-600' }}">
                                            #{{ $latestBuild->id }} ({{ ucfirst($latestBuild->status) }})
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="mt-5 pt-3 border-t border-gray-100 flex items-center justify-between gap-2">
                            <a
                                href="{{ route('builder.projects.show', $project) }}"
                                class="text-xs font-semibold text-[#1A1D1F] hover:text-black hover:underline flex items-center gap-1"
                            >
                                Kelola & Build →
                            </a>

                            <div class="flex items-center gap-1.5">
                                <a
                                    href="{{ route('builder.editor.show', $project) }}"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100"
                                    title="Edit File Template"
                                >
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </a>

                                <form method="POST" action="{{ route('builder.projects.destroy', $project) }}" onsubmit="return confirm('Hapus proyek ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="p-1.5 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50"
                                        title="Hapus Proyek"
                                    >
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Template Overview --}}
    <div class="mt-12 pt-8 border-t border-gray-200">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Template Sumber Terdaftar</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach ($templates as $tmpl)
                <div class="p-5 rounded-2xl border border-gray-200 bg-white flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-base font-bold text-gray-900">{{ $tmpl->name }}</h3>
                            <span class="px-2 py-0.5 text-[11px] font-mono rounded bg-gray-100 text-gray-600">v{{ $tmpl->version }}</span>
                        </div>
                        <p class="text-xs text-gray-500 leading-relaxed">{{ $tmpl->description }}</p>
                        <div class="mt-3 text-[11px] font-mono text-gray-400 truncate">
                            Source: {{ $tmpl->source_path }}
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-gray-100">
                        <a
                            href="{{ route('builder.projects.create', ['template_id' => $tmpl->id]) }}"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 hover:text-emerald-700"
                        >
                            + Gunakan Template Ini
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

@include('builder.partials.cleanup-modal')
@endsection
