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

        return view('dashboard', compact('folders'));
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

        return view('folders', compact('screenshots', 'folderName', 'folders', 'activeWindows'));
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

            // 2. Hitung jumlah screenshot per user_name dari hasil_ss
            $ssCounts = [];
            try {
                $ssCountRes = Http::withHeaders([
                    'apikey'        => $supabaseKey,
                    'Authorization' => 'Bearer ' . $supabaseKey,
                ])
                ->timeout(5)
                ->get(rtrim($supabaseUrl, '/') . '/hasil_ss', [
                    'select' => 'user_name',
                ]);

                if ($ssCountRes->successful()) {
                    $ssCounts = collect($ssCountRes->json())
                        ->groupBy('user_name')
                        ->map(fn ($group) => $group->count())
                        ->all();
                }
            } catch (\Throwable $e) {
                // Ignore count error
            }

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

        // Fallback default jika database kosong atau error
        if (empty($folders)) {
            $folders = [
                [
                    'id'      => 'task-automation',
                    'title'   => 'Task Automation',
                    'items'   => 3,
                    'theme'   => 'lavender',
                    'avatars' => ['alex'],
                    'extra'   => 0,
                ],
                [
                    'id'      => 'notes-highlights',
                    'title'   => 'Notes & Highlights',
                    'items'   => 17,
                    'theme'   => 'sky',
                    'avatars' => ['elena', 'marcus'],
                    'extra'   => 0,
                ],
                [
                    'id'      => 'billing-invoicing',
                    'title'   => 'Billing & Invoicing',
                    'items'   => 5,
                    'theme'   => 'peach',
                    'avatars' => ['alex', 'sara', 'marcus'],
                    'extra'   => 3,
                ],
            ];
        }

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
