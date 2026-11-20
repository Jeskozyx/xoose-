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
        $supabaseUrl = config('services.supabase.url');
        $supabaseKey = config('services.supabase.key');

        $folders = [];

        try {
            $response = Http::withHeaders([
                'apikey'        => $supabaseKey,
                'Authorization' => 'Bearer ' . $supabaseKey,
            ])
            ->timeout(6)
            ->get(rtrim($supabaseUrl, '/') . '/user_responses', [
                'select' => '*',
                'order'  => 'id.asc',
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
                    $folders = collect($rows)->map(function ($row, $index) use ($themes, $avatarPresets) {
                        $theme = $themes[$index % count($themes)];
                        $avatars = $avatarPresets[$index % count($avatarPresets)];
                        $title = !empty($row['nama']) ? $row['nama'] : 'Folder ' . ($index + 1);

                        return [
                            'id'      => 'folder-' . ($row['id'] ?? $index),
                            'db_id'   => $row['id'] ?? null,
                            'title'   => $title,
                            'items'   => 1,
                            'theme'   => $theme,
                            'avatars' => $avatars,
                            'extra'   => 0,
                            'status'  => $row['status'] ?? 'Active',
                            'date'    => $row['tanggal_date'] ?? null,
                        ];
                    })->all();
                }
            } else {
                Log::warning('Supabase response error: ' . $response->status() . ' - ' . $response->body());
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal koneksi ke Supabase user_responses: ' . $e->getMessage());
        }

        // Fallback default jika database kosong atau koneksi error
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

        return view('dashboard', compact('folders'));
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
