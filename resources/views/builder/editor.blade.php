@extends('layouts.app')

@section('title', 'Template Editor — ' . $project->name)

@php
    $activeMenu = 'builder';
@endphp

@section('content')
<div class="h-full flex flex-col overflow-hidden bg-[#FAFAFB]">
    {{-- Header --}}
    <div class="px-8 py-5 bg-white border-b border-gray-100 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                <a href="{{ route('builder.projects.index') }}" class="hover:text-gray-900">Application Builder</a>
                <span>/</span>
                <a href="{{ route('builder.projects.show', $project) }}" class="hover:text-gray-900">{{ $project->name }}</a>
                <span>/</span>
                <span class="text-gray-800 font-medium">Restricted Code Editor</span>
            </div>
            <h1 class="text-xl font-bold text-gray-900">Editor Template Terkontrol</h1>
        </div>

        <div class="flex items-center gap-3">
            <span class="text-xs text-emerald-600 font-medium flex items-center gap-1.5 bg-emerald-50 px-3 py-1.5 rounded-lg border border-emerald-100">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span>Sandbox Terisolasi (Auto-Backup)</span>
            </span>
            <a
                href="{{ route('builder.projects.show', $project) }}"
                class="px-4 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
            >
                Kembali ke Proyek
            </a>
        </div>
    </div>

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="px-8 py-3 bg-emerald-50 border-b border-emerald-200 text-emerald-800 text-xs flex items-center justify-between">
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="px-8 py-3 bg-rose-50 border-b border-rose-200 text-rose-800 text-xs flex items-center justify-between">
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- Editor Layout --}}
    <div class="flex-1 flex overflow-hidden">
        {{-- File List Allowlist Sidebar --}}
        <div class="w-64 bg-gray-50 border-r border-gray-200 p-4 flex flex-col justify-between overflow-y-auto">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-3">File yang Diizinkan</span>
                <div class="space-y-1">
                    @foreach ($allowlist as $file)
                        @php $isActive = $file === $selectedFile; @endphp
                        <a
                            href="{{ route('builder.editor.show', [$project, 'file' => $file]) }}"
                            class="flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-mono transition-colors {{ $isActive ? 'bg-white text-gray-900 font-bold shadow-sm border border-gray-200' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}"
                        >
                            <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <span class="truncate">{{ $file }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-[11px] leading-relaxed mt-4">
                <strong>Catatan Keamanan:</strong> Hanya file tampilan dan konfigurasi dalam allowlist yang dapat diedit. File engine dan script sensitif dikunci.
            </div>
        </div>

        {{-- Code Area --}}
        <div class="flex-1 flex flex-col overflow-hidden bg-[#0D1117]">
            <form method="POST" action="{{ route('builder.editor.save', $project) }}" class="h-full flex flex-col">
                @csrf
                <input type="hidden" name="file" value="{{ $selectedFile }}">

                {{-- Editor Subheader --}}
                <div class="px-5 py-2.5 bg-[#161B22] border-b border-gray-800 flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2 text-gray-300 font-mono">
                        <span class="text-gray-500">File:</span>
                        <span class="font-bold text-white">{{ $selectedFile }}</span>
                    </div>

                    <button
                        type="submit"
                        class="px-4 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs transition-colors shadow-sm"
                    >
                        💾 Simpan Perubahan
                    </button>
                </div>

                {{-- Textarea Editor --}}
                <div class="flex-1 p-4 overflow-hidden">
                    <textarea
                        name="content"
                        class="w-full h-full bg-transparent text-gray-200 font-mono text-xs leading-relaxed focus:outline-none resize-none"
                        spellcheck="false"
                    >{{ $content }}</textarea>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
