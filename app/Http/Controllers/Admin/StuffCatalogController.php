<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportStuffCatalogRequest;
use App\Jobs\ImportStuffCatalog;
use App\Models\StuffCatalogImport;
use App\Services\StuffCatalogMetadata;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class StuffCatalogController extends Controller
{
    public function index(StuffCatalogMetadata $metadata): View
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
            'catalogCount' => $metadata->count(),
            'uniqueItemCount' => $metadata->uniqueItemCount(),
            'catalogTypeCount' => $metadata->typeCount(),
        ]);
    }

    public function store(ImportStuffCatalogRequest $request): RedirectResponse|JsonResponse
    {
        $queuedImports = [];

        foreach ($request->file('catalog_files', []) as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $storedPath = null;
            $history = null;

            try {
                $storedPath = $file->store('stuff-catalog-imports', 'local');

                if ($storedPath === false) {
                    throw new RuntimeException('ذخیره موقت فایل روی سرور انجام نشد.');
                }

                $history = StuffCatalogImport::query()->create([
                    'user_id' => $request->user()->id,
                    'file_name' => $file->getClientOriginalName(),
                    'status' => StuffCatalogImport::STATUS_QUEUED,
                    'file_size' => (int) ($file->getSize() ?: 0),
                    'started_at' => now(),
                ]);
                ImportStuffCatalog::dispatch($history->id, $storedPath)->onQueue('catalog-imports');
                $queuedImports[] = [
                    'id' => $history->id,
                    'status_url' => route('admin.stuff-catalog.imports.show', $history),
                ];
            } catch (Throwable $exception) {
                if (is_string($storedPath)) {
                    Storage::disk('local')->delete($storedPath);
                }

                if ($history instanceof StuffCatalogImport) {
                    $history->update([
                        'status' => StuffCatalogImport::STATUS_FAILED,
                        'error_message' => Str::limit($exception->getMessage(), 2000),
                        'completed_at' => now(),
                    ]);
                }

                report($exception);

                $message = $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'پردازش فایل به‌دلیل خطای داخلی کامل نشد.';

                return back()->withErrors(['catalog_files' => $message]);
            }
        }

        if ($request->ajax()) {
            return response()->json([
                'message' => 'فایل دریافت شد و پردازش آن در صف قرار گرفت.',
                'imports' => $queuedImports,
            ], 202);
        }

        return redirect()->route('admin.stuff-catalog.index')->with('success', 'فایل دریافت شد و پردازش آن در صف قرار گرفت.');
    }

    public function show(StuffCatalogImport $stuffCatalogImport): JsonResponse
    {
        return response()->json([
            'id' => $stuffCatalogImport->id,
            'status' => $stuffCatalogImport->status,
            'progress_percent' => $stuffCatalogImport->progress_percent,
            'processed_rows' => $stuffCatalogImport->processed_rows,
            'new_rows' => $stuffCatalogImport->new_rows,
            'updated_rows' => $stuffCatalogImport->updated_rows,
            'unchanged_rows' => $stuffCatalogImport->unchanged_rows,
            'skipped_rows' => $stuffCatalogImport->skipped_rows,
            'error_message' => $stuffCatalogImport->status === StuffCatalogImport::STATUS_FAILED
                ? Str::limit((string) $stuffCatalogImport->error_message, 500)
                : null,
        ]);
    }
}
