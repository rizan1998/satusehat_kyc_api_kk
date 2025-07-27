<?php

namespace App\Console;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{

    protected function schedule(Schedule $schedule)
    {
        Log::info('Scheduler dijalankan pada: ' . now());
        // Log::info('Jadwal aktif: ' . json_encode($activeSchedules));

        $activeSchedules = $this->getSyncSchedules();

        foreach ($activeSchedules as $job) {
            $time = date('H:i', strtotime($job->waktu));
            Log::info("Menjadwalkan job {$job->kategori} pada pukul {$time}");
            $schedule->command('fetch:ci3')->dailyAt($time)
                ->timezone('Asia/Jakarta')
                ->appendOutputTo(storage_path('logs/sync_obat.log'));
        }
    }

    protected function getSyncSchedules()
    {
        return DB::table('kk_schedule_sync_obat')->where('status', 'active')->get();
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
