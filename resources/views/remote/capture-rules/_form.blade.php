{{-- Partial Form Tambah Aturan Domain Browser & Jendela Aplikasi --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
    {{-- Form Tambah Browser Domain Rule --}}
    <div class="p-6 rounded-2xl border border-gray-200 bg-white">
        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <div>
                <h3 class="text-sm font-bold text-gray-900">Tambah Aturan Domain Browser</h3>
                <p class="text-xs text-gray-500 mt-0.5">Mencocokkan seluruh path pada domain target (tanpa wildcard/skema).</p>
            </div>
            <span class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 font-mono text-[11px] font-semibold">browser_tab</span>
        </div>

        <form method="POST" action="{{ route('remote.capture-rules.rules.store') }}" class="mt-4 space-y-3.5">
            @csrf
            <input type="hidden" name="scope" value="{{ $scope }}">
            <input type="hidden" name="rule_type" value="browser">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Domain Target *</label>
                    <input
                        type="text"
                        name="domain"
                        value="{{ old('rule_type', 'browser') === 'browser' ? old('domain') : '' }}"
                        required
                        placeholder="konstantaedu.com"
                        class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm font-mono focus:outline-none focus:border-gray-900"
                    >
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">ID Aturan (opsional)</label>
                    <input
                        type="text"
                        name="id"
                        value="{{ old('rule_type', 'browser') === 'browser' ? old('id') : '' }}"
                        placeholder="konstanta-domain"
                        class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm font-mono focus:outline-none focus:border-gray-900"
                    >
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <span class="block text-xs font-semibold text-gray-700 mb-1.5">Target Browser *</span>
                    <div class="flex flex-wrap gap-3 text-xs text-gray-700">
                        @foreach (['chrome' => 'Chrome', 'edge' => 'Edge', 'firefox' => 'Firefox'] as $bKey => $bLabel)
                            <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox" name="browsers[]" value="{{ $bKey }}" checked class="rounded border-gray-300">
                                <span>{{ $bLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <span class="block text-xs font-semibold text-gray-700 mb-1.5">Skema URL</span>
                    <div class="flex flex-wrap gap-3 text-xs text-gray-700">
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="schemes[]" value="https" checked class="rounded border-gray-300">
                            <span class="font-mono">https</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="schemes[]" value="http" class="rounded border-gray-300">
                            <span class="font-mono">http</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-5 pt-1 text-xs text-gray-700">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="include_subdomains" value="0">
                    <input type="checkbox" name="include_subdomains" value="1" checked class="rounded border-gray-300">
                    <span>Sertakan Subdomain (<span class="font-mono">*.domain</span>)</span>
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1" checked class="rounded border-gray-300">
                    <span>Langsung Aktifkan</span>
                </label>
            </div>

            <div class="pt-1">
                <button type="submit" class="px-4 py-2 rounded-xl bg-[#1A1D1F] hover:bg-black text-white text-xs font-semibold cursor-pointer">
                    + Simpan Aturan Domain (Live)
                </button>
            </div>
        </form>
    </div>

    {{-- Form Tambah Application Window Rule (MSS Terpisah) --}}
    <div class="p-6 rounded-2xl border border-gray-200 bg-white">
        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <div>
                <h3 class="text-sm font-bold text-gray-900">Tambah Aturan Jendela Aplikasi (MSS)</h3>
                <p class="text-xs text-gray-500 mt-0.5">Menangkap via MSS hanya saat jendela proses fokus (<span class="font-mono">GetForegroundWindow</span>).</p>
            </div>
            <span class="px-2 py-0.5 rounded-md bg-sky-50 text-sky-700 font-mono text-[11px] font-semibold">active_window</span>
        </div>

        <form method="POST" action="{{ route('remote.capture-rules.rules.store') }}" class="mt-4 space-y-3.5">
            @csrf
            <input type="hidden" name="scope" value="{{ $scope }}">
            <input type="hidden" name="rule_type" value="window">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Nama Proses Windows *</label>
                    <input
                        type="text"
                        name="process"
                        value="{{ old('rule_type') === 'window' ? old('process') : '' }}"
                        required
                        placeholder="chrome.exe"
                        class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm font-mono focus:outline-none focus:border-gray-900"
                    >
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">ID Aturan (opsional)</label>
                    <input
                        type="text"
                        name="id"
                        value="{{ old('rule_type') === 'window' ? old('id') : '' }}"
                        placeholder="chrome-window"
                        class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm font-mono focus:outline-none focus:border-gray-900"
                    >
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Filter Kata di Judul Jendela (opsional)</label>
                <input
                    type="text"
                    name="window_title_contains"
                    value="{{ old('rule_type') === 'window' ? old('window_title_contains') : '' }}"
                    placeholder="Contoh: Tryout atau Konstanta"
                    class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-gray-900"
                >
            </div>

            <div class="flex items-center gap-5 pt-1 text-xs text-gray-700">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1" checked class="rounded border-gray-300">
                    <span>Langsung Aktifkan</span>
                </label>
            </div>

            <div class="pt-1">
                <button type="submit" class="px-4 py-2 rounded-xl bg-white hover:bg-gray-50 border border-gray-300 text-gray-900 text-xs font-semibold cursor-pointer">
                    + Simpan Aturan Window (Live)
                </button>
            </div>
        </form>
    </div>
</div>
