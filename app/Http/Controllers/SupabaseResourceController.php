<?php

namespace App\Http\Controllers;

use App\Services\SupabaseResourceService;
use Illuminate\Http\Request;

class SupabaseResourceController extends Controller
{
    public function __construct(protected SupabaseResourceService $resourceService) {}

    /**
     * Get real-time Supabase resource usage stats.
     */
    public function stats()
    {
        $stats = $this->resourceService->getStats();

        return response()->json([
            'success' => true,
            'data'    => $stats,
        ]);
    }

    /**
     * Handle cleanup requests for Supabase database rows and storage files.
     */
    public function cleanup(Request $request)
    {
        $request->validate([
            'action'    => 'required|in:screenshots,users,all,user',
            'user_name' => 'nullable|string|max:100',
        ]);

        $action = $request->input('action');
        $userName = $request->input('user_name');

        $message = '';
        $hasDeleted = true;

        switch ($action) {
            case 'screenshots':
                $res = $this->resourceService->deleteScreenshots();
                $message = "Pembersihan selesai: {$res['db_records']} baris database dan {$res['storage_files']} file storage screenshot berhasil dibersihkan.";
                break;

            case 'users':
                $deleted = $this->resourceService->deleteUsers();
                $message = "Pembersihan selesai: {$deleted} data profil pengguna berhasil dibersihkan.";
                break;

            case 'user':
                if (empty($userName)) {
                    return back()->with('error', 'Nama pengguna yang ingin dihapus wajib dipilih.');
                }
                $ssRes = $this->resourceService->deleteScreenshots($userName);
                $uDeleted = $this->resourceService->deleteUsers($userName);
                $message = "Pembersihan selesai: Data user '{$userName}' berhasil dibersihkan ({$ssRes['db_records']} screenshot, {$ssRes['storage_files']} storage file, {$uDeleted} user profile).";
                break;

            case 'all':
            default:
                $result = $this->resourceService->cleanAll();
                $message = "Reset Supabase Selesai! Berhasil membersihkan {$result['screenshots']} screenshot, {$result['storage_files']} file storage, dan {$result['users']} pengguna.";
                break;
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }
}
