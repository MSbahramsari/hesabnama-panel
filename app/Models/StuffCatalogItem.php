<?php

namespace App\Models;

use Database\Factories\StuffCatalogItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'item_id',
    'description',
    'type',
    'vat',
    'source_created_date',
    'effective_date',
    'expiration_date',
    'source_updated_date',
    'source_hash',
])]
class StuffCatalogItem extends Model
{
    /** @use HasFactory<StuffCatalogItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['vat' => 'decimal:2'];
    }
}
