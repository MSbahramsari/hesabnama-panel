<?php

namespace App\Services\Moadian;

class MoadianNormalizer
{
    /** @param array<string, mixed> $payload */
    public function normalize(array $payload): string
    {
        $flattened = $this->flatten($payload);
        ksort($flattened, SORT_STRING);

        return implode('#', array_map($this->normalizeValue(...), array_values($flattened)));
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<string, mixed>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $flattened = array_merge($flattened, $this->flatten($value, $path));

                continue;
            }

            $flattened[$path] = $value;
        }

        return $flattened;
    }

    private function normalizeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null || $value === '') {
            return '#';
        }

        return str_replace('#', '##', (string) $value);
    }
}
