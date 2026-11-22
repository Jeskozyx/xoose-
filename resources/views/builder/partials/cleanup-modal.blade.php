{{-- Modal Bersihkan Resource Supabase --}}
<div id="cleanupModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
    <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-gray-100 overflow-hidden transform transition-all">
        <div class="p-6 border-b border-gray-100 flex items-start justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-900">Kelola Sumber Daya Supabase</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Hapus data rekaman dan file storage untuk menghemat kuota cloud.</p>
                </div>
            </div>
            <button type="button" onclick="closeCleanupModal()" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        {{-- Live Stats Bar --}}
        <div class="bg-gray-50 px-6 py-3 border-b border-gray-100 flex items-center justify-around text-center text-xs">
            <div>
                <span class="text-gray-500 block text-[11px]">Database Screenshot</span>
                <span id="stat-screenshots" class="font-bold text-gray-900 font-mono text-sm">Memuat...</span>
            </div>
            <div class="h-6 w-px bg-gray-200"></div>
            <div>
                <span class="text-gray-500 block text-[11px]">Profil Pengguna</span>
                <span id="stat-users" class="font-bold text-gray-900 font-mono text-sm">Memuat...</span>
            </div>
        </div>

        {{-- Form Cleanup --}}
        <form method="POST" action="{{ route('builder.resources.cleanup') }}" id="cleanupForm" onsubmit="return confirmSubmitCleanup(event)">
            @csrf
            <div class="p-6 space-y-4">
                <div class="text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Pilih Aksi Pembersihan:</div>

                <label class="flex items-start gap-3 p-3.5 rounded-xl border border-gray-200 hover:border-gray-300 hover:bg-gray-50/50 cursor-pointer transition-colors">
                    <input type="radio" name="action" value="screenshots" class="mt-0.5 text-rose-600 focus:ring-rose-500" checked onchange="toggleUserSelect(false)">
                    <div>
                        <span class="block text-xs font-bold text-gray-900">Hapus Semua Screenshot & File Storage</span>
                        <span class="block text-[11px] text-gray-500 mt-0.5">Mengosongkan tabel <code class="font-mono text-gray-700">hasil_ss</code> dan menghapus semua foto di Supabase Storage. Profil user tetap aman.</span>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-3.5 rounded-xl border border-gray-200 hover:border-gray-300 hover:bg-gray-50/50 cursor-pointer transition-colors">
                    <input type="radio" name="action" value="users" class="mt-0.5 text-rose-600 focus:ring-rose-500" onchange="toggleUserSelect(false)">
                    <div>
                        <span class="block text-xs font-bold text-gray-900">Hapus Semua Pengguna (User Responses)</span>
                        <span class="block text-[11px] text-gray-500 mt-0.5">Mengosongkan daftar pengguna/folder di tabel <code class="font-mono text-gray-700">user_responses</code>.</span>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-3.5 rounded-xl border border-rose-200 bg-rose-50/30 hover:bg-rose-50/60 cursor-pointer transition-colors">
                    <input type="radio" name="action" value="all" class="mt-0.5 text-rose-600 focus:ring-rose-500" onchange="toggleUserSelect(false)">
                    <div>
                        <span class="block text-xs font-bold text-rose-900">Reset Total (Hapus Screenshot, Storage & Pengguna)</span>
                        <span class="block text-[11px] text-rose-600 mt-0.5">Mengosongkan seluruh data Supabase secara tuntas (Fresh Reset).</span>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-3.5 rounded-xl border border-gray-200 hover:border-gray-300 hover:bg-gray-50/50 cursor-pointer transition-colors">
                    <input type="radio" name="action" value="user" class="mt-0.5 text-rose-600 focus:ring-rose-500" id="radio-user" onchange="toggleUserSelect(true)">
                    <div class="flex-1">
                        <span class="block text-xs font-bold text-gray-900">Hapus Pengguna Tertentu</span>
                        <span class="block text-[11px] text-gray-500 mt-0.5">Hapus screenshot dan profil untuk satu nama user saja.</span>

                        <div id="user-select-container" class="hidden mt-2.5">
                            <select name="user_name" id="user-select" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-black">
                                <option value="">Pilih Pengguna...</option>
                            </select>
                        </div>
                    </div>
                </label>
            </div>

            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
                <button
                    type="button"
                    onclick="closeCleanupModal()"
                    class="px-4 py-2 rounded-xl border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-100 transition-colors cursor-pointer"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    id="submit-cleanup-btn"
                    class="px-5 py-2 rounded-xl bg-rose-600 text-white text-xs font-semibold hover:bg-rose-700 transition-colors shadow-sm inline-flex items-center gap-1.5 cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    <span>Konfirmasi & Bersihkan</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCleanupModal(preselectedUser = null) {
        const modal = document.getElementById('cleanupModal');
        if (!modal) return;
        modal.classList.remove('hidden');
        loadResourceStats(preselectedUser);
    }

    function closeCleanupModal() {
        const modal = document.getElementById('cleanupModal');
        if (!modal) return;
        modal.classList.add('hidden');
    }

    function toggleUserSelect(show) {
        const container = document.getElementById('user-select-container');
        if (!container) return;
        if (show) {
            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
        }
    }

    function loadResourceStats(preselectedUser = null) {
        const ssEl = document.getElementById('stat-screenshots');
        const uEl = document.getElementById('stat-users');
        const selectEl = document.getElementById('user-select');

        if (ssEl) ssEl.textContent = 'Memuat...';
        if (uEl) uEl.textContent = 'Memuat...';

        fetch('{{ route("builder.resources.stats") }}')
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    if (ssEl) ssEl.textContent = res.data.total_screenshots.toLocaleString() + ' baris';
                    if (uEl) uEl.textContent = res.data.total_users.toLocaleString() + ' user';

                    if (selectEl) {
                        selectEl.innerHTML = '<option value="">Pilih Pengguna...</option>';
                        if (res.data.users && res.data.users.length > 0) {
                            res.data.users.forEach(u => {
                                const opt = document.createElement('option');
                                opt.value = u;
                                opt.textContent = u;
                                if (preselectedUser && preselectedUser.toLowerCase() === u.toLowerCase()) {
                                    opt.selected = true;
                                }
                                selectEl.appendChild(opt);
                            });
                        }

                        if (preselectedUser) {
                            const radioUser = document.getElementById('radio-user');
                            if (radioUser) {
                                radioUser.checked = true;
                                toggleUserSelect(true);
                            }
                        }
                    }
                }
            })
            .catch(() => {
                if (ssEl) ssEl.textContent = 'Gagal memuat';
                if (uEl) uEl.textContent = 'Gagal memuat';
            });
    }

    function confirmSubmitCleanup(e) {
        const action = document.querySelector('input[name="action"]:checked').value;
        let warning = 'Apakah Anda yakin ingin melakukan pembersihan ini? Data yang dihapus tidak dapat dipulihkan.';

        if (action === 'all') {
            warning = 'PERINGATAN: Anda akan menghapus SELURUH screenshot, file storage, dan semua pengguna di Supabase! Lanjutkan?';
        } else if (action === 'screenshots') {
            warning = 'Apakah Anda yakin ingin menghapus SELURUH tangkapan layar dan file di Supabase Storage?';
        } else if (action === 'users') {
            warning = 'Apakah Anda yakin ingin menghapus SEMUA profil pengguna?';
        } else if (action === 'user') {
            const u = document.getElementById('user-select').value;
            if (!u) {
                alert('Silakan pilih nama pengguna terlebih dahulu.');
                e.preventDefault();
                return false;
            }
            warning = `Apakah Anda yakin ingin menghapus seluruh data dan screenshot milik user "${u}"?`;
        }

        if (!confirm(warning)) {
            e.preventDefault();
            return false;
        }

        const btn = document.getElementById('submit-cleanup-btn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = 'Sedang memproses...';
        }
        return true;
    }
</script>
