<?php

namespace App\Console\Commands;

use App\Services\ProductImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Import the NEXT pending Syntech batch CSV (drafts) from storage/imports/syntech/, one per run.
 * Designed for a temporary cron job (no browser timeouts). Finished files move to done/.
 *
 *   php artisan syntech:import-next            # import one batch
 *   php artisan syntech:import-next --preview  # scan only
 */
class ImportSyntechBatch extends Command
{
    protected $signature = 'syntech:import-next {--preview : Scan only, no changes}';

    protected $description = 'Import the next pending Syntech batch CSV from storage/imports/syntech';

    public function handle(ProductImportService $import): int
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);
        $dir = config('syntech.import_dir', storage_path('imports/syntech'));
        @mkdir($dir.'/done', 0775, true);

        $lock = fopen($dir.'/import.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->warn('Previous batch still running, exiting.');

            return self::SUCCESS;
        }

        $files = glob($dir.'/syntech_*.csv') ?: [];
        sort($files);
        if ($files === []) {
            $this->info('No pending Syntech batches. You can delete the import cron job.');
            file_put_contents($dir.'/import_history.log', date('c')." ALL BATCHES DONE\n", FILE_APPEND);

            return self::SUCCESS;
        }
        $file = $files[0];
        $name = basename($file);

        if ($this->option('preview')) {
            $r = $import->previewFromPath($file);
            $this->line("$name preview: ".json_encode(array_diff_key($r, ['samples' => 1])));

            return self::SUCCESS;
        }

        $t = microtime(true);
        $r = $import->importFromPath($file);
        $summary = sprintf('%s %s: %s (%.0fs)', date('c'), $name,
            json_encode(array_intersect_key($r, array_flip(['imported', 'updated', 'skipped', 'skippedNonIt', 'skippedNoImage', 'skippedNoPrice', 'errors']))),
            microtime(true) - $t);
        rename($file, $dir.'/done/'.$name);
        file_put_contents($dir.'/import_history.log', $summary."\n", FILE_APPEND);
        Log::info('Syntech batch import '.$summary);
        $this->info($summary);

        return self::SUCCESS;
    }
}
