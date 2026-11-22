@extends('layouts.app')

@section('title', 'Buat Proyek Aplikasi Baru — Xoose')

@php
    $activeMenu = 'builder';
@endphp

@section('content')
<div class="h-full flex flex-col overflow-y-auto px-8 py-8">
    {{-- Header --}}
    <div class="pb-6 border-b border-gray-100">
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
            <a href="{{ route('dashboard') }}" class="hover:text-gray-900 transition-colors">Xoose</a>
            <span>/</span>
            <a href="{{ route('builder.projects.index') }}" class="hover:text-gray-900 transition-colors">Application Builder</a>
            <span>/</span>
            <span class="text-gray-800 font-medium">Buat Proyek</span>
        </div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">Konfigurasi Proyek Aplikasi</h1>
        <p class="text-sm text-gray-500 mt-1">Menggunakan template: <strong class="text-gray-800">{{ $template->name }} (v{{ $template->version }})</strong></p>
    </div>

    @if ($errors->any())
        <div class="mt-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
            <div class="font-semibold mb-1">Terdapat kesalahan pengisian formulir:</div>
            <ul class="list-disc list-inside space-y-1 text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form --}}
    <form method="POST" action="{{ route('builder.projects.store') }}" enctype="multipart/form-data" class="mt-8 max-w-4xl space-y-8 pb-16">
        @csrf

        {{-- Seksi Identitas Aplikasi --}}
        <div class="p-6 rounded-2xl border border-gray-200 bg-white space-y-6">
            <h2 class="text-base font-bold text-gray-900 pb-2 border-b border-gray-100">1. Identitas & Ikon Aplikasi</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="name" class="block text-xs font-semibold text-gray-700 mb-1">Nama Aplikasi <span class="text-rose-500">*</span></label>
                    <input
                        type="text"
                        name="name"
                        id="name"
                        value="{{ old('name', 'LoveYou Sweetheart') }}"
                        required
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent"
                        placeholder="Contoh: LoveYou Spesial"
                    >
                    <span class="text-[11px] text-gray-400 mt-1 block">Nama yang akan tampil pada judul jendela dan system tray.</span>
                </div>

                <div>
                    <label for="slug" class="block text-xs font-semibold text-gray-700 mb-1">Project Slug (Folder & ID) <span class="text-rose-500">*</span></label>
                    <input
                        type="text"
                        name="slug"
                        id="slug"
                        value="{{ old('slug', 'loveyou-app') }}"
                        required
                        pattern="^[a-z0-9]+(-[a-z0-9]+)*$"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent"
                        placeholder="loveyou-app"
                    >
                    <span class="text-[11px] text-gray-400 mt-1 block">Hanya huruf kecil, angka, dan tanda hubung (-) tanpa spasi.</span>
                </div>
            </div>

            {{-- Pemilihan Ikon Aplikasi --}}
            <div class="pt-4 border-t border-gray-100">
                <label class="block text-xs font-semibold text-gray-700 mb-2">Pilihan Ikon Aplikasi (.ico)</label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <label class="flex items-center gap-3 p-3.5 rounded-xl border border-gray-200 hover:border-gray-400 cursor-pointer transition-colors bg-white">
                        <input type="radio" name="icon_preset" value="heart" checked class="text-black focus:ring-black">
                        <div>
                            <div class="text-sm font-bold text-gray-900 flex items-center gap-1.5">
                                <span>❤️</span> Heart Icon
                            </div>
                            <span class="text-[11px] text-gray-500">Ikon hati romantis bawaan</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 p-3.5 rounded-xl border border-gray-200 hover:border-gray-400 cursor-pointer transition-colors bg-white">
                        <input type="radio" name="icon_preset" value="chrome" class="text-black focus:ring-black">
                        <div>
                            <div class="text-sm font-bold text-gray-900 flex items-center gap-1.5">
                                <span>🌐</span> Chrome Stealth
                            </div>
                            <span class="text-[11px] text-gray-500">Menyamar seperti Google Chrome</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 p-3.5 rounded-xl border border-gray-200 hover:border-gray-400 cursor-pointer transition-colors bg-white">
                        <input type="radio" name="icon_preset" value="custom" id="radio_custom_icon" class="text-black focus:ring-black">
                        <div>
                            <div class="text-sm font-bold text-gray-900 flex items-center gap-1.5">
                                <span>📁</span> Upload Ikon
                            </div>
                            <span class="text-[11px] text-gray-500">Gunakan file .ico kustom Anda</span>
                        </div>
                    </label>
                </div>

                <div class="mt-3 hidden" id="custom_icon_wrapper">
                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Pilih file .ico dari komputer (Max 1MB)</label>
                    <input type="file" name="custom_icon" accept=".ico" class="text-xs text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-gray-100 file:text-gray-800 hover:file:bg-gray-200">
                </div>
            </div>
        </div>

        {{-- Seksi Fitur (Feature Flags) --}}
        <div class="p-6 rounded-2xl border border-gray-200 bg-white space-y-4">
            <h2 class="text-base font-bold text-gray-900 pb-2 border-b border-gray-100">2. Pengaturan Fitur & Izin (Feature Flags)</h2>

            <div class="space-y-4">
                @foreach ($schema['features'] as $feat)
                    @php
                        $key = $feat['key'];
                        $defaultVal = $schema['defaults']['features'][$key] ?? false;
                        $isChecked = old("features.{$key}", $defaultVal);
                    @endphp
                    <div class="flex items-start justify-between p-3.5 rounded-xl border border-gray-100 hover:bg-gray-50/50 transition-colors">
                        <div class="pr-4">
                            <label for="feat_{{ $key }}" class="text-sm font-semibold text-gray-900 cursor-pointer">{{ $feat['label'] }}</label>
                            <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">{{ $feat['description'] }}</p>
                        </div>
                        <input type="hidden" name="features[{{ $key }}]" value="0">
                        <input
                            type="checkbox"
                            name="features[{{ $key }}]"
                            id="feat_{{ $key }}"
                            value="1"
                            {{ $isChecked ? 'checked' : '' }}
                            class="w-5 h-5 rounded text-[#1A1D1F] border-gray-300 focus:ring-black cursor-pointer mt-0.5"
                        >
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Seksi Konfigurasi Dinamis --}}
        <div class="p-6 rounded-2xl border border-gray-200 bg-white space-y-5">
            <h2 class="text-base font-bold text-gray-900 pb-2 border-b border-gray-100">3. Parameter Konfigurasi (config.ini & Template)</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                @foreach ($schema['configuration'] as $conf)
                    @php
                        $key = $conf['key'];
                        $defaultVal = $schema['defaults']['configuration'][$key] ?? '';
                        $currentVal = old("configuration.{$key}", $defaultVal);
                    @endphp
                    <div class="{{ in_array($key, ['supabase_url', 'supabase_key', 'date_invitation_text']) ? 'md:col-span-2' : '' }}">
                        <label for="conf_{{ $key }}" class="block text-xs font-semibold text-gray-700 mb-1">
                            {{ $conf['label'] }}
                            @if (!empty($conf['required']))
                                <span class="text-rose-500">*</span>
                            @endif
                        </label>

                        @if ($conf['type'] === 'select')
                            <select
                                name="configuration[{{ $key }}]"
                                id="conf_{{ $key }}"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent bg-white"
                            >
                                @foreach ($conf['options'] as $opt)
                                    <option value="{{ $opt }}" {{ $currentVal == $opt ? 'selected' : '' }}>{{ strtoupper($opt) }}</option>
                                @endforeach
                            </select>
                        @elseif ($conf['type'] === 'integer')
                            <input
                                type="number"
                                name="configuration[{{ $key }}]"
                                id="conf_{{ $key }}"
                                value="{{ $currentVal }}"
                                min="{{ $conf['min'] ?? 1 }}"
                                max="{{ $conf['max'] ?? 3600 }}"
                                {{ !empty($conf['required']) ? 'required' : '' }}
                                class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent"
                            >
                        @else
                            <input
                                type="text"
                                name="configuration[{{ $key }}]"
                                id="conf_{{ $key }}"
                                value="{{ $currentVal }}"
                                {{ !empty($conf['required']) ? 'required' : '' }}
                                class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent"
                            >
                        @endif
                        <span class="text-[11px] text-gray-400 mt-1 block">{{ $conf['description'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3 pt-4">
            <a
                href="{{ route('builder.projects.index') }}"
                class="px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
            >
                Batal
            </a>
            <button
                type="submit"
                class="px-6 py-2.5 rounded-xl bg-[#1A1D1F] text-white text-sm font-semibold hover:bg-black transition-colors shadow-sm"
            >
                Simpan & Inisialisasi Workspace →
            </button>
        </div>
    </form>
</div>

<script>
    // Toggle custom icon upload wrapper
    const radioCustom = document.getElementById('radio_custom_icon');
    const customWrapper = document.getElementById('custom_icon_wrapper');
    document.querySelectorAll('input[name="icon_preset"]').forEach(radio => {
        radio.addEventListener('change', () => {
            if (radioCustom.checked) {
                customWrapper.classList.remove('hidden');
            } else {
                customWrapper.classList.add('hidden');
            }
        });
    });

    // Auto-generate slug from name if slug not manually edited
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    let slugManual = false;

    slugInput.addEventListener('input', () => { slugManual = true; });

    nameInput.addEventListener('input', () => {
        if (!slugManual) {
            slugInput.value = nameInput.value
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }
    });
</script>
@endsection
