<?php

namespace App\Jobs;

use App\Models\StuffCatalogImport as StuffCatalogImportModel;
use App\Services\StuffCatalogImporter;
use App\Services\StuffCatalogImportResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportStuffCatalog implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $importId,
        public string $storedPath,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(StuffCatalogImporter $importer): void
    {
        $history = StuffCatalogImportModel::query()->findOrFail($this->importId);
        $history->update([
            'status' => StuffCatalogImportModel::STATUS_PROCESSING,
            'progress_percent' => 0,
            'error_message' => null,
        ]);
        $absolutePath = Storage::disk('local')->path($this->storedPath);

        if (! Storage::disk('local')->exists($this->storedPath)) {
            throw new RuntimeException('فایل موقت کاتالوگ روی سرور پیدا نشد.');
        }

        $lastSavedPercentage = -1;

        try {
            $result = $importer->import(
                $absolutePath,
                function (int $processedBytes, int $totalBytes, int $processedRows, StuffCatalogImportResult $partialResult) use ($history, &$lastSavedPercentage): void {
                    $percentage = min(99, (int) floor(($processedBytes / $totalBytes) * 100));

                    if ($percentage === $lastSavedPercentage) {
                        return;
                    }

                    $lastSavedPercentage = $percentage;
                    $history->update([
                        'progress_percent' => $percentage,
                        'processed_bytes' => $processedBytes,
                        'processed_rows' => $processedRows,
                        'new_rows' => $partialResult->newRows,
                        'updated_rows' => $partialResult->updatedRows,
                        'unchanged_rows' => $partialResult->unchangedRows,
                        'skipped_rows' => $partialResult->skippedRows,
                    ]);
                },
            );
            $history->update([
                'status' => StuffCatalogImportModel::STATUS_COMPLETED,
                'progress_percent' => 100,
                'processed_bytes' => $history->file_size,
                'new_rows' => $result->newRows,
                'updated_rows' => $result->updatedRows,
                'unchanged_rows' => $result->unchangedRows,
                'skipped_rows' => $result->skippedRows,
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->markAsFailed($exception);

            throw $exception;
        } finally {
            Storage::disk('local')->delete($this->storedPath);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markAsFailed($exception ?? new RuntimeException('پردازش فایل به‌صورت غیرمنتظره متوقف شد.'));
        Storage::disk('local')->delete($this->storedPath);
    }

    private function markAsFailed(Throwable $exception): void
    {
        StuffCatalogImportModel::query()->whereKey($this->importId)->update([
            'status' => StuffCatalogImportModel::STATUS_FAILED,
            'error_message' => Str::limit($exception->getMessage(), 2000),
            'completed_at' => now(),
        ]);
    }
}
