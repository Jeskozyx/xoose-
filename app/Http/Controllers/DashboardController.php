<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * Tampilkan halaman Dashboard dengan folder dari Supabase user_responses.
     */
    public function index()
    {
        $folders = $this->fetchFolders();
        $workflows = []; // Tidak ada dummy: hanya muncul jika ada data

        return view('dashboard', compact('folders', 'workflows'));
    }

    /**
     * Tampilkan halaman Folders dengan data dari tabel Supabase hasil_ss (difilter per user).
     */
    public function folders(Request $request)
    {
        $supabaseUrl = config('services.supabase.url');
        $supabaseKey = config('services.supabase.key');

        $folders = $this->fetchFolders();

        // Default ke folder user pertama jika tidak ada query ?folder=...
        $defaultFolder = !empty($folders[0]['title']) ? $folders[0]['title'] : 'all';
        $folderName = $request->query('folder', $defaultFolder);

        $screenshots = [];
        $activeWindows = [];

        try {
            $queryParams = [
                'select' => '*',
                'order'  => 'id.desc',
            ];

            // Filter per user: jika bukan 'all', hanya ambil screenshot milik user_name tersebut
            if ($folderName !== 'all' && $folderName !== 'hasil_ss') {
                $queryParams['user_name'] = 'eq.' . $folderName;
            }

            $response = Http::withHeaders([
                'apikey'        => $supabaseKey,
                'Authorization' => 'Bearer ' . $supabaseKey,
            ])
            ->timeout(8)
            ->get(rtrim($supabaseUrl, '/') . '/hasil_ss', $queryParams);

            if ($response->successful()) {
                $screenshots = $response->json();

                // Kumpulkan daftar unique active window untuk filter
                $activeWindows = collect($screenshots)
                    ->pluck('active_window')
                    ->filter(fn ($w) => !empty(trim((string) $w)))
                    ->map(fn ($w) => trim((string) $w))
                    ->unique()
                    ->values()
                    ->take(12)
                    ->all();
            } else {
                Log::warning('Supabase hasil_ss fetch failed: ' . $response->status() . ' - ' . $response->body());
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal mengambil data dari Supabase hasil_ss: ' . $e->getMessage());
        }

        // Ambil detail data dari tabel user_responses untuk nama folder ini
        $userResponse = null;
        if ($folderName !== 'all' && $folderName !== 'hasil_ss') {
            try {
                $uRes = Http::withHeaders([
                    'apikey'        => $supabaseKey,
                    'Authorization' => 'Bearer ' . $supabaseKey,
                ])
                ->timeout(5)
                ->get(rtrim($supabaseUrl, '/') . '/user_responses', [
                    'select' => '*',
                    'nama'   => 'eq.' . $folderName,
                    'limit'  => 1,
                ]);

                if ($uRes->successful() && !empty($uRes->json())) {
                    $userResponse = $uRes->json()[0];
                }
            } catch (\Throwable $e) {
                Log::warning('Gagal mengambil user_response detail: ' . $e->getMessage());
            }
        }

        // Tentukan metadata device_name, resolution, dan status dari tabel hasil_ss & user_responses
        $latestSS = !empty($screenshots) ? $screenshots[0] : null;
        $folderDeviceName = $latestSS['device_name'] ?? (!empty($screenshots) ? collect($screenshots)->pluck('device_name')->filter()->first() : null);
        $folderResolution = $latestSS['resolution'] ?? (!empty($screenshots) ? collect($screenshots)->pluck('resolution')->filter()->first() : null);
        $folderSsStatus = $latestSS['status'] ?? null;
        $folderUserStatus = $userResponse['status'] ?? null;

        return view('folders', compact(
            'screenshots',
            'folderName',
            'folders',
            'activeWindows',
            'userResponse',
            'folderDeviceName',
            'folderResolution',
            'folderSsStatus',
            'folderUserStatus'
        ));
    }

    /**
     * Helper untuk mengambil data folders dari user_responses dan menghitung jumlah screenshot per user.
     */
    protected function fetchFolders(): array
    {
        $supabaseUrl = config('services.supabase.url');
        $supabaseKey = config('services.supabase.key');

        $folders = [];

        try {
            // 1. Ambil data users dari user_responses
            $response = Http::withHeaders([
                'apikey'        => $supabaseKey,
                'Authorization' => 'Bearer ' . $supabaseKey,
            ])
            ->timeout(6)
            ->get(rtrim($supabaseUrl, '/') . '/user_responses', [
                'select' => '*',
                'order'  => 'id.desc',
            ]);

            if ($response->successful()) {
                $rows = $response->json();
                $themes = ['lavender', 'sky', 'peach'];
                $avatarPresets = [
                    ['alex'],
                    ['elena', 'marcus'],
                    ['alex', 'sara', 'marcus'],
                    ['sara'],
                    ['alex', 'elena'],
                    ['marcus'],
                ];

                if (!empty($rows)) {
                    // Kelompokkan nama user agar unik (jika user input form lebih dari sekali)
                    $uniqueUsers = collect($rows)->unique('nama')->values();

                    // 2. Hitung jumlah screenshot per user secara paralel menggunakan Prefer: count=exact
                    // Ini akurat 100% dan tidak terpotong oleh batasan 1000 baris Supabase
                    $ssCounts = [];
                    try {
                        $countResponses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($uniqueUsers, $supabaseUrl, $supabaseKey) {
                            return $uniqueUsers->map(function ($row) use ($pool, $supabaseUrl, $supabaseKey) {
                                $userName = !empty($row['nama']) ? $row['nama'] : '';
                                return $pool->as($userName)->withHeaders([
                                    'apikey'        => $supabaseKey,
                                    'Authorization' => 'Bearer ' . $supabaseKey,
                                    'Prefer'        => 'count=exact',
                                    'Range'         => '0-0',
                                ])->timeout(4)->get(rtrim($supabaseUrl, '/') . '/hasil_ss', [
                                    'select'    => 'id',
                                    'user_name' => 'eq.' . $userName,
                                ]);
                            });
                        });

                        foreach ($countResponses as $userName => $res) {
                            if ($res instanceof \Illuminate\Http\Client\Response && ($res->status() === 200 || $res->status() === 206)) {
                                $cr = $res->header('Content-Range'); // e.g. "0-0/9" or "*/0"
                                if ($cr && preg_match('/\/(\d+)$/', $cr, $m)) {
                                    $ssCounts[$userName] = (int) $m[1];
                                } else {
                                    $ssCounts[$userName] = count($res->json() ?: []);
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Pool count hasil_ss failed: ' . $e->getMessage());
                    }

                    $folders = $uniqueUsers->map(function ($row, $index) use ($themes, $avatarPresets, $ssCounts) {
                        $theme = $themes[$index % count($themes)];
                        $avatars = $avatarPresets[$index % count($avatarPresets)];
                        $title = !empty($row['nama']) ? $row['nama'] : 'Folder ' . ($index + 1);
                        
                        // Hitung jumlah tangkapan layar riil milik user ini
                        $itemsCount = $ssCounts[$title] ?? 0;

                        return [
                            'id'         => 'folder-' . ($row['id'] ?? $index),
                            'db_id'      => $row['id'] ?? null,
                            'title'      => $title,
                            'items'      => $itemsCount,
                            'theme'      => $theme,
                            'avatars'    => $avatars,
                            'extra'      => 0,
                            'status'     => $row['status'] ?? 'Active',
                            'date'       => $row['tanggal_date'] ?? null,
                            'created_at' => $row['created_at'] ?? null,
                        ];
                    })->all();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal koneksi ke Supabase user_responses: ' . $e->getMessage());
        }

        // Tidak ada data dummy fallback: hanya tampilkan data nyata dari Supabase user_responses
        return $folders;
    }

    /**
     * Tambah folder baru ke Supabase user_responses.
     */
    public function storeFolder(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
        ]);

        $supabaseUrl = config('services.supabase.url');
        $supabaseKey = config('services.supabase.key');

        try {
            $response = Http::withHeaders([
                'apikey'        => $supabaseKey,
                'Authorization' => 'Bearer ' . $supabaseKey,
                'Content-Type'  => 'application/json',
                'Prefer'        => 'return=representation',
            ])->post(rtrim($supabaseUrl, '/') . '/user_responses', [
                'nama'         => $validated['nama'],
                'tanggal_date' => now()->toDateString(),
                'status'       => 'yes',
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Folder berhasil disimpan ke Supabase!',
                    'data'    => $response->json(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Supabase error: ' . $response->body(),
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
