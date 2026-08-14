<?php

namespace App\Models;

use Database\Factories\StuffCatalogImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'file_name',
    'status',
    'progress_percent',
    'file_size',
    'processed_bytes',
    'processed_rows',
    'new_rows',
    'updated_rows',
    'unchanged_rows',
    'skipped_rows',
    'error_message',
    'started_at',
    'completed_at',
])]
class StuffCatalogImport extends Model
{
    /** @use HasFactory<StuffCatalogImportFactory> */
    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'progress_percent' => 'integer',
            'file_size' => 'integer',
            'processed_bytes' => 'integer',
            'processed_rows' => 'integer',
            'new_rows' => 'integer',
            'updated_rows' => 'integer',
            'unchanged_rows' => 'integer',
            'skipped_rows' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
