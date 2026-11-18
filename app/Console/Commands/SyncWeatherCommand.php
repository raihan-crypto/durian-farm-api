<?php

namespace App\Console\Commands;

use App\Jobs\FetchGardenWeatherJob;
use Illuminate\Console\Command;

class SyncWeatherCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'durian:sync-weather {--garden= : UUID kebun tertentu} {--sync : Jalankan secara sinkron tanpa antrean}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi data cuaca perkebunan dari layanan Weather API eksternal';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $gardenId = $this->option('garden') ?: null;

        if ($this->option('sync')) {
            FetchGardenWeatherJob::dispatchSync($gardenId);
            $this->info('Sinkronisasi cuaca selesai dijalankan secara sinkron.');
        } else {
            FetchGardenWeatherJob::dispatch($gardenId);
            $this->info('Job sinkronisasi cuaca berhasil dimasukkan ke antrean.');
        }

        return self::SUCCESS;
    }
}
