<?php

namespace App\Console\Commands;

use App\Services\StuffCatalogImporter;
use App\Services\StuffCatalogMetadata;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('stuff:import {files* : مسیر یک یا چند فایل CSV رسمی کالا و خدمت}')]
#[Description('Import official goods and services catalog CSV files into the local searchable catalog')]
class ImportStuffCatalog extends Command
{
    public function handle(StuffCatalogImporter $importer, StuffCatalogMetadata $metadata): int
    {
        foreach ((array) $this->argument('files') as $file) {
            $path = $this->resolvePath((string) $file);

            try {
                $result = $importer->import($path);
            } catch (RuntimeException $exception) {
                $metadata->forget();
                $this->components->error($exception->getMessage());

                return self::FAILURE;
            }

            $this->components->info(basename($path).' با موفقیت پردازش شد.');
            $this->line("جدید: {$result->newRows}");
            $this->line("به‌روزشده: {$result->updatedRows}");
            $this->line("بدون تغییر: {$result->unchangedRows}");
            $this->line("ردشده: {$result->skippedRows}");
        }

        $metadata->forget();

        return self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
