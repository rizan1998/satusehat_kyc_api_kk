<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Log;

class ProcessSyncBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $table,
        public array $data,
        public string $action
    ) {}

    public function handle()
    {
        try {
            DB::transaction(function () {
                match ($this->action) {
                    'upsert' => $this->processUpsert(),
                    'insert' => $this->processInsert(),
                    default => throw new \Exception("Invalid action: {$this->action}")
                };

                Log::info("Successfully processed {$this->action} action for table {$this->table}");
            });
        } catch (\Exception $e) {
            Log::error("Failed to process {$this->action} action for table {$this->table}", [
                'error' => $e->getMessage(),
                'data_count' => count($this->data),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Re-throw the exception after logging
        }
    }

    private function processInsert()
    {
        try {
            DB::table($this->table)->insert($this->data);
            Log::debug("Inserted data into {$this->table}", ['count' => count($this->data)]);
        } catch (\Exception $e) {
            Log::error("Failed to insert data into {$this->table}", [
                'error' => $e->getMessage(),
                'data_sample' => $this->data[0] ?? null
            ]);
            throw $e;
        }
    }

    private function processUpsert()
    {
        if (empty($this->data)) {
            Log::warning("Empty data set for upsert on table {$this->table}");
            return;
        }

        try {
            $firstRow = $this->data[0];
            $columns = array_keys($firstRow);

            // Explicitly set the unique key (use 'id' if exists, otherwise use all columns)
            $uniqueBy = in_array('id', $columns) ? ['id'] : $columns;

            // Log the data being processed for debugging
            Log::debug("Upsert data sample:", ['sample_row' => $firstRow]);
            Log::debug("Columns to update:", ['columns' => $columns]);

            // Get all columns except the unique key for updating
            $updateColumns = array_diff($columns, $uniqueBy);

            DB::table($this->table)->upsert(
                $this->data,
                $uniqueBy,
                $updateColumns
            );

            // Verify the update
            $updatedCount = DB::table($this->table)
                ->whereIn('id', array_column($this->data, 'id'))
                ->count();

            Log::info("Upsert completed for table {$this->table}", [
                'input_count' => count($this->data),
                'updated_count' => $updatedCount
            ]);
        } catch (\Exception $e) {
            Log::error("Upsert failed for table {$this->table}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'sample_data' => $this->data[0] ?? null
            ]);
            throw $e;
        }
    }

    // private function processUpsert()
    // {
    //     if (empty($this->data)) {
    //         Log::warning("Empty data set for upsert on table {$this->table}");
    //         return; // No data to process
    //     }

    //     try {
    //         $firstRow = $this->data[0];
    //         $columns = array_keys($firstRow);

    //         // Dynamic unique key detection (prioritize 'id', fallback to all columns)
    //         $uniqueBy = in_array('id', $columns) ? ['id'] : $columns;
    //         Log::debug("Upserting data into {$this->table}", [
    //             'unique_by' => $uniqueBy,
    //             'data_count' => count($this->data)
    //         ]);

    //         DB::table($this->table)->upsert(
    //             $this->data,
    //             $uniqueBy,       // Columns to check for duplicates
    //             $this->getColumnsToUpdate($columns, $uniqueBy) // Columns to update
    //         );

    //         Log::info("Successfully upserted data into {$this->table}", ['count' => count($this->data)]);
    //     } catch (\Exception $e) {
    //         Log::error("Failed to upsert data into {$this->table}", [
    //             'error' => $e->getMessage(),
    //             'unique_by' => $uniqueBy ?? null,
    //             'data_sample' => $this->data[0] ?? null
    //         ]);
    //         throw $e;
    //     }
    // }

    private function getColumnsToUpdate(array $allColumns, array $uniqueBy): array
    {
        return array_diff($allColumns, $uniqueBy);
    }
}



// class ProcessSyncBatch implements ShouldQueue
// {
//     public function __construct(
//         public string $table,
//         public array $data,
//         public string $action
//     ) {}

//     public function handle()
//     {
//         DB::transaction(function () {
//             match ($this->action) {
//                 'upsert' => $this->processUpsert(),
//                 default => DB::table($this->table)->insert($this->data)
//             };
//         });
//     }

//     private function processUpsert()
//     {
//         $firstRow = $this->data[0];
//         $columns = array_keys($firstRow);

//         DB::table($this->table)->upsert(
//             $this->data,
//             ['id'], // Primary key
//             array_diff($columns, ['id']) // Kolom yang diupdate
//         );
//     }
// }
