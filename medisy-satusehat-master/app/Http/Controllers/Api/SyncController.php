<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Jobs\ProcessSyncBatch;
use App\Http\Controllers\Controller;

class SyncController extends Controller
{
    public function handle(Request $request)
    {
        $validated = $request->validate([
            'table' => 'required|string|in:kk_obat,kk_detail_obat,kk_kategori_obat,kk_stok_apotek',
            'data' => 'required|array',
            'action' => 'required|in:insert,upsert'
        ]);

        dispatch(new ProcessSyncBatch(
            $validated['table'],
            $validated['data'],
            $validated['action']
        ))->onQueue('sync');

        return response()->json(['status' => 'queued']);
    }
}
