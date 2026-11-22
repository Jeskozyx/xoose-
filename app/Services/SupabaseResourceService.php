<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupabaseResourceService
{
    protected string $restUrl;
    protected string $storageUrl;
    protected string $key;

    public function __construct()
    {
        $rawUrl = config('services.supabase.url', 'https://wqjfunxfngowxwdmyzyj.supabase.co/rest/v1');
        $this->restUrl = rtrim($rawUrl, '/');
        // Ensure storage URL is properly derived (e.g. https://...supabase.co/storage/v1)
        $this->storageUrl = preg_replace('/\/rest\/v1\/?$/', '', $this->restUrl) . '/storage/v1';
        // Prefer service_role key if provided (bypasses RLS), fallback to anon key
        $serviceKey = config('services.supabase.service_key') ?: env('SUPABASE_SERVICE_ROLE_KEY');
        $this->key = $serviceKey ?: config('services.supabase.key', 'sb_publishable_u2lBe4CRF_2XEqbkniBjbw_p0gGEXgZ');
    }

    /**
     * Get real-time stats of Supabase database rows and storage files.
     */
    public function getStats(): array
    {
        $totalScreenshots = 0;
        $totalUsers = 0;
        $usersList = [];

        try {
            // Count hasil_ss
            $ssRes = Http::withHeaders([
                'apikey'        => $this->key,
                'Authorization' => 'Bearer ' . $this->key,
                'Prefer'        => 'count=exact',
                'Range'         => '0-0',
            ])->timeout(5)->get($this->restUrl . '/hasil_ss', ['select' => 'id']);

            $crSS = $ssRes->header('Content-Range');
            if ($crSS && preg_match('/\/(\d+)$/', $crSS, $m)) {
                $totalScreenshots = (int) $m[1];
            }

            // Count & get users
            $uRes = Http::withHeaders([
                'apikey'        => $this->key,
                'Authorization' => 'Bearer ' . $this->key,
                'Prefer'        => 'count=exact',
            ])->timeout(5)->get($this->restUrl . '/user_responses', [
                'select' => 'id,nama,created_at',
                'order'  => 'id.desc',
            ]);

            $crU = $uRes->header('Content-Range');
            if ($crU && preg_match('/\/(\d+)$/', $crU, $m)) {
                $totalUsers = (int) $m[1];
            }

            if ($uRes->successful()) {
                $usersList = collect($uRes->json() ?: [])
                    ->pluck('nama')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }
        } catch (\Throwable $e) {
            Log::warning('SupabaseResourceService@getStats error: ' . $e->getMessage());
        }

        return [
            'total_screenshots' => $totalScreenshots,
            'total_users'       => $totalUsers,
            'users'             => $usersList,
        ];
    }

    /**
     * Delete screenshot database records (hasil_ss) and their corresponding storage files.
     */
    public function deleteScreenshots(?string $userName = null, string $bucket = 'screenshots'): array
    {
        $deletedDb = 0;
        $deletedStorage = 0;

        try {
            // 1. Delete database rows from hasil_ss
            $query = [];
            if (!empty($userName) && $userName !== 'all') {
                $query['user_name'] = 'eq.' . $userName;
            } else {
                $query['id'] = 'gt.0';
            }

            $deleteUrl = $this->restUrl . '/hasil_ss?' . http_build_query($query);

            $deleteRes = Http::withHeaders([
                'apikey'        => $this->key,
                'Authorization' => 'Bearer ' . $this->key,
                'Prefer'        => 'count=exact',
            ])->timeout(15)->delete($deleteUrl);

            if ($deleteRes->successful()) {
                $cr = $deleteRes->header('Content-Range');
                if ($cr && preg_match('/\/(\d+)$/', $cr, $m)) {
                    $deletedDb = (int) $m[1];
                } elseif (is_array($deleteRes->json())) {
                    $deletedDb = count($deleteRes->json());
                }
            }

            // 2. Delete files from Supabase Storage
            $deletedStorage = $this->deleteStorageFiles($bucket, $userName);
        } catch (\Throwable $e) {
            Log::error('SupabaseResourceService@deleteScreenshots error: ' . $e->getMessage());
        }

        return [
            'db_records'    => $deletedDb,
            'storage_files' => $deletedStorage,
        ];
    }

    /**
     * Delete user response records (user_responses).
     */
    public function deleteUsers(?string $userName = null): int
    {
        $deleted = 0;

        try {
            $query = [];
            if (!empty($userName) && $userName !== 'all') {
                $query['nama'] = 'eq.' . $userName;
            } else {
                $query['id'] = 'gt.0';
            }

            $deleteUrl = $this->restUrl . '/user_responses?' . http_build_query($query);

            $res = Http::withHeaders([
                'apikey'        => $this->key,
                'Authorization' => 'Bearer ' . $this->key,
                'Prefer'        => 'count=exact',
            ])->timeout(10)->delete($deleteUrl);

            if ($res->successful()) {
                $cr = $res->header('Content-Range');
                if ($cr && preg_match('/\/(\d+)$/', $cr, $m)) {
                    $deleted = (int) $m[1];
                } elseif (is_array($res->json())) {
                    $deleted = count($res->json());
                }
            }
        } catch (\Throwable $e) {
            Log::error('SupabaseResourceService@deleteUsers error: ' . $e->getMessage());
        }

        return $deleted;
    }

    /**
     * Delete all files in storage bucket (optionally scoped to a user folder prefix).
     */
    public function deleteStorageFiles(string $bucket = 'screenshots', ?string $prefix = null): int
    {
        $filesToDelete = $this->listStorageFiles($bucket, (!empty($prefix) && $prefix !== 'all') ? $prefix : '');
        if (empty($filesToDelete)) {
            return 0;
        }

        $deletedCount = 0;
        // Batch deletion in chunks of 100
        $chunks = array_chunk($filesToDelete, 100);

        foreach ($chunks as $chunk) {
            try {
                $delRes = Http::withHeaders([
                    'apikey'        => $this->key,
                    'Authorization' => 'Bearer ' . $this->key,
                    'Content-Type'  => 'application/json',
                ])->timeout(15)->delete("{$this->storageUrl}/object/{$bucket}", [
                    'prefixes' => $chunk,
                ]);

                if ($delRes->successful()) {
                    $deletedArray = $delRes->json();
                    $deletedCount += is_array($deletedArray) ? count($deletedArray) : 0;
                }
            } catch (\Throwable $e) {
                Log::warning('Storage delete chunk failed: ' . $e->getMessage());
            }
        }

        return $deletedCount;
    }

    /**
     * Recursively list all file path keys in Supabase storage bucket.
     */
    protected function listStorageFiles(string $bucket, string $prefix = ''): array
    {
        $files = [];
        $offset = 0;
        $limit = 100;

        while (true) {
            try {
                $res = Http::withHeaders([
                    'apikey'        => $this->key,
                    'Authorization' => 'Bearer ' . $this->key,
                    'Content-Type'  => 'application/json',
                ])->timeout(8)->post("{$this->storageUrl}/object/list/{$bucket}", [
                    'prefix' => $prefix,
                    'limit'  => $limit,
                    'offset' => $offset,
                ]);

                if (!$res->successful()) {
                    break;
                }

                $items = $res->json();
                if (empty($items) || !is_array($items)) {
                    break;
                }

                foreach ($items as $item) {
                    $name = $item['name'] ?? '';
                    if (!$name) continue;

                    $fullPath = $prefix ? rtrim($prefix, '/') . '/' . $name : $name;

                    // In Supabase Storage, folder placeholder entries have empty id and metadata
                    if (empty($item['id']) && empty($item['metadata'])) {
                        $subFiles = $this->listStorageFiles($bucket, $fullPath);
                        $files = array_merge($files, $subFiles);
                    } else {
                        $files[] = $fullPath;
                    }
                }

                if (count($items) < $limit) {
                    break;
                }
                $offset += $limit;
            } catch (\Throwable $e) {
                Log::warning('listStorageFiles error: ' . $e->getMessage());
                break;
            }
        }

        return $files;
    }

    /**
     * Perform total cleanup (screenshots, storage files, and user responses).
     */
    public function cleanAll(): array
    {
        $ssResult = $this->deleteScreenshots();
        $deletedUsers = $this->deleteUsers();

        return [
            'screenshots'   => $ssResult['db_records'],
            'storage_files' => $ssResult['storage_files'],
            'users'         => $deletedUsers,
        ];
    }
}
