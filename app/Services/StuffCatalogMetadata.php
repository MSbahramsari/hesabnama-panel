<?php

namespace App\Services;

use App\Models\StuffCatalogItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class StuffCatalogMetadata
{
    private const CATALOG_COUNT_KEY = 'stuff-catalog:count';

    private const UNIQUE_ITEM_COUNT_KEY = 'stuff-catalog:unique-item-count';

    private const TYPE_COUNT_KEY = 'stuff-catalog:type-count';

    private const TYPES_KEY = 'stuff-catalog:types';

    private const VATS_KEY = 'stuff-catalog:vats';

    public function count(): int
    {
        return Cache::rememberForever(self::CATALOG_COUNT_KEY, fn (): int => StuffCatalogItem::query()->count());
    }

    public function uniqueItemCount(): int
    {
        return Cache::rememberForever(
            self::UNIQUE_ITEM_COUNT_KEY,
            fn (): int => StuffCatalogItem::query()->distinct()->count('item_id'),
        );
    }

    public function typeCount(): int
    {
        return Cache::rememberForever(
            self::TYPE_COUNT_KEY,
            fn (): int => StuffCatalogItem::query()->whereNotNull('type')->distinct()->count('type'),
        );
    }

    /** @return Collection<int, string> */
    public function types(): Collection
    {
        return collect($this->rememberArray(
            self::TYPES_KEY,
            fn (): array => StuffCatalogItem::query()->whereNotNull('type')->distinct()->orderBy('type')->pluck('type')->all(),
        ));
    }

    /** @return Collection<int, string> */
    public function vats(): Collection
    {
        return collect($this->rememberArray(
            self::VATS_KEY,
            fn (): array => StuffCatalogItem::query()->distinct()->orderBy('vat')->pluck('vat')->all(),
        ));
    }

    public function forget(): void
    {
        foreach ([
            self::CATALOG_COUNT_KEY,
            self::UNIQUE_ITEM_COUNT_KEY,
            self::TYPE_COUNT_KEY,
            self::TYPES_KEY,
            self::VATS_KEY,
        ] as $key) {
            Cache::forget($key);
        }
    }

    /**
     * @param  callable(): array<int, mixed>  $resolve
     * @return array<int, mixed>
     */
    private function rememberArray(string $key, callable $resolve): array
    {
        $value = Cache::rememberForever($key, $resolve);

        if (is_array($value)) {
            return $value;
        }

        Cache::forget($key);
        $resolved = $resolve();
        Cache::forever($key, $resolved);

        return $resolved;
    }
}
