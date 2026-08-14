<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportStuffCatalogRequest;
use App\Models\StuffCatalogImport;
use App\Models\StuffCatalogItem;
use App\Services\StuffCatalogImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class StuffCatalogController extends Controller
{
    public function index(): View
    {
        $imports = StuffCatalogImport::query()
            ->with('user:id,name')
            ->latest('started_at')
            ->paginate(15);
        $lastSuccessfulImport = StuffCatalogImport::query()
            ->where('status', StuffCatalogImport::STATUS_COMPLETED)
            ->latest('completed_at')
            ->first();

        return view('admin.stuff-catalog.index', [
            'imports' => $imports,
            'lastSuccessfulImport' => $lastSuccessfulImport,
            'catalogCount' => StuffCatalogItem::query()->count(),
            'uniqueItemCount' => StuffCatalogItem::query()->distinct()->count('item_id'),
            'catalogTypeCount' => StuffCatalogItem::query()->whereNotNull('type')->distinct()->count('type'),
        ]);
    }

    public function store(ImportStuffCatalogRequest $request, StuffCatalogImporter $importer): RedirectResponse
    {
        $totals = ['new' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0];

        foreach ($request->file('catalog_files', []) as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $history = StuffCatalogImport::query()->create([
                'user_id' => $request->user()->id,
                'file_name' => $file->getClientOriginalName(),
                'status' => StuffCatalogImport::STATUS_PROCESSING,
                'started_at' => now(),
            ]);

            try {
                $path = $file->getRealPath();

                if ($path === false) {
                    throw new RuntimeException('فایل آپلودشده روی سرور قابل خواندن نیست.');
                }

                $result = $importer->import($path);
                $history->update([
                    'status' => StuffCatalogImport::STATUS_COMPLETED,
                    'new_rows' => $result->newRows,
                    'updated_rows' => $result->updatedRows,
                    'unchanged_rows' => $result->unchangedRows,
                    'skipped_rows' => $result->skippedRows,
                    'completed_at' => now(),
                ]);
                $totals['new'] += $result->newRows;
                $totals['updated'] += $result->updatedRows;
                $totals['unchanged'] += $result->unchangedRows;
                $totals['skipped'] += $result->skippedRows;
            } catch (Throwable $exception) {
                $history->update([
                    'status' => StuffCatalogImport::STATUS_FAILED,
                    'error_message' => Str::limit($exception->getMessage(), 2000),
                    'completed_at' => now(),
                ]);
                report($exception);

                $message = $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'پردازش فایل به‌دلیل خطای داخلی کامل نشد.';

                return back()->withErrors(['catalog_files' => $message]);
            }
        }

        $message = "بروزرسانی کاتالوگ انجام شد؛ {$totals['new']} ردیف جدید و {$totals['updated']} ردیف به‌روزشده است.";

        return redirect()->route('admin.stuff-catalog.index')->with('success', $message);
    }
}
