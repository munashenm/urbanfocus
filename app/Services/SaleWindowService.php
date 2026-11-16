<?php

namespace App\Services;

use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SaleWindowService
{
    public function applyCsv(string $path, bool $dryRun): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException("CSV not found or not readable: {$path}");
        }

        $found = 0;
        $notFound = 0;
        $changed = 0;
        $invalid = 0;
        $backup = [];
        $updates = [];

        foreach ($this->rows($path) as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                $invalid++;
                continue;
            }

            $product = Product::query()
                ->whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)])
                ->first();

            if (! $product) {
                $notFound++;
                continue;
            }

            $found++;
            $next = $this->nextValues($product, $row);
            if ($next === null) {
                $invalid++;
                continue;
            }

            if ($this->unchanged($product, $next)) {
                continue;
            }

            $changed++;
            $backup[] = $this->snapshot($product);
            $updates[] = [$product, $next];
        }

        $backupPath = null;
        if (! $dryRun && $updates !== []) {
            $backupPath = $this->writeBackup($backup);
            foreach ($updates as [$product, $next]) {
                $product->update($next);
            }
        }

        return [
            'found' => $found,
            'not_found' => $notFound,
            'changed' => $changed,
            'invalid' => $invalid,
            'dry_run' => $dryRun,
            'backup' => $backupPath,
        ];
    }

    public function clear(?string $label, bool $dryRun): array
    {
        $snapshots = $this->latestSnapshots();
        $query = Product::query()->where(function ($sale) {
            $sale->whereNotNull('sale_price')
                ->orWhereNotNull('promo_label')
                ->orWhereNotNull('sale_starts_at')
                ->orWhereNotNull('sale_ends_at');
        });

        if ($label !== null && $label !== '') {
            $query->where('promo_label', $label);
        }

        $restored = 0;
        foreach ($query->get() as $product) {
            $previous = $snapshots[mb_strtolower((string) $product->sku)] ?? [
                'sale_price' => null,
                'sale_starts_at' => null,
                'sale_ends_at' => null,
                'promo_label' => null,
            ];

            $restored++;
            if (! $dryRun) {
                $product->update($previous);
            }
        }

        return [
            'restored' => $restored,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    protected function rows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("CSV not found or not readable: {$path}");
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return [];
        }

        $keys = array_map(fn ($column) => strtolower(trim((string) $column)), $header);
        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null] || $line === []) {
                continue;
            }
            $row = [];
            foreach ($keys as $index => $key) {
                $row[$key] = trim((string) ($line[$index] ?? ''));
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>|null
     */
    protected function nextValues(Product $product, array $row): ?array
    {
        if (! isset($row['sale_price']) || $row['sale_price'] === '' || ! is_numeric($row['sale_price'])) {
            return null;
        }

        $salePrice = round((float) $row['sale_price'], 2);
        if ($salePrice <= 0 || $salePrice >= (float) $product->price) {
            return null;
        }

        try {
            $starts = $this->parseDate($row['sale_starts_at'] ?? '');
            $ends = $this->parseDate($row['sale_ends_at'] ?? '');
        } catch (\Throwable) {
            return null;
        }

        if ($starts && $ends && $ends->lte($starts)) {
            return null;
        }

        $label = trim((string) ($row['promo_label'] ?? ''));

        return [
            'sale_price' => $salePrice,
            'sale_starts_at' => $starts,
            'sale_ends_at' => $ends,
            'promo_label' => $label !== '' ? $label : null,
        ];
    }

    protected function parseDate(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return Carbon::parse($value, 'Africa/Johannesburg');
    }

    /**
     * @param  array<string, mixed>  $next
     */
    protected function unchanged(Product $product, array $next): bool
    {
        return $this->money($product->sale_price) === $this->money($next['sale_price'])
            && $this->stamp($product->sale_starts_at) === $this->stamp($next['sale_starts_at'])
            && $this->stamp($product->sale_ends_at) === $this->stamp($next['sale_ends_at'])
            && (string) $product->promo_label === (string) ($next['promo_label'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(Product $product): array
    {
        return [
            'sku' => (string) $product->sku,
            'sale_price' => $product->sale_price !== null ? $this->money($product->sale_price) : null,
            'sale_starts_at' => $this->stamp($product->sale_starts_at),
            'sale_ends_at' => $this->stamp($product->sale_ends_at),
            'promo_label' => $product->promo_label,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function writeBackup(array $rows): string
    {
        $relative = 'sale-backups/sale-apply-'.now()->timezone('Africa/Johannesburg')->format('Ymd-His').'.jsonl';
        $body = implode("\n", array_map(
            fn (array $row) => json_encode($row, JSON_UNESCAPED_SLASHES),
            $rows
        ));
        Storage::disk('local')->put($relative, $body."\n");

        return $relative;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function latestSnapshots(): array
    {
        $files = Storage::disk('local')->files('sale-backups');
        if ($files === []) {
            return [];
        }

        usort($files, fn (string $a, string $b) => strcmp($b, $a));
        $contents = Storage::disk('local')->get($files[0]);
        $snapshots = [];
        foreach (preg_split("/\r\n|\n|\r/", (string) $contents) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $row = json_decode($line, true);
            if (! is_array($row) || empty($row['sku'])) {
                continue;
            }
            $snapshots[mb_strtolower((string) $row['sku'])] = [
                'sale_price' => $row['sale_price'] ?? null,
                'sale_starts_at' => ! empty($row['sale_starts_at'])
                    ? Carbon::parse($row['sale_starts_at'], 'Africa/Johannesburg')
                    : null,
                'sale_ends_at' => ! empty($row['sale_ends_at'])
                    ? Carbon::parse($row['sale_ends_at'], 'Africa/Johannesburg')
                    : null,
                'promo_label' => $row['promo_label'] ?? null,
            ];
        }

        return $snapshots;
    }

    protected function money(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    protected function stamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->timezone('Africa/Johannesburg')->format('Y-m-d H:i:s');
    }
}
