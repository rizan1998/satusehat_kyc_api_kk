<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSyncBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class FetchCI3Data extends Command
{
    protected $signature = 'fetch:ci3';
    protected $description = 'Tarik data dari CI3 dan masukkan ke job ProcessSyncBatch';

    // app/Console/Commands/FetchCI3Data.php
    public function handle()
    {
        $tables = ['kk_obat'];
        $baseUrl = env('KESTURI_BASE_URL');
        $limit = 200; // Atur batch size di Laravel

        foreach ($tables as $table) {
            $offset = 0;

            do {
                $payload =  [
                    'table' => $table,
                    'offset' => $offset,
                    'limit' => $limit // Kirim limit ke CI3
                ];

                $response = Http::timeout(60)
                    ->post("$baseUrl/klinik_api/Obat/sync_obat_api", $payload);

                if ($response->failed()) {
                    Log::error("Gagal fetch data", [
                        'table' => $table,
                        'offset' => $offset,
                        'response' => $response->body()
                    ]);
                    break;
                }

                $data = $response->json();
                if (empty($data['data'])) {
                    break;
                }

                // Dispatch ke queue
                dispatch(new ProcessSyncBatch(
                    $table,
                    $data['data'],
                    'upsert'
                ))->onQueue('sync');

                $this->info(sprintf(
                    '[%s] Queued %d rows (Offset: %d)',
                    $table,
                    $data['count'],
                    $offset
                ));

                $offset = $data['next_offset']; // Update offset

            } while ($data['count'] > 0);
        }
    }
}
