<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Harga emas SPOT dunia dalam rupiah per gram, dari sumber gratis tanpa API key.
 * Bukan harga jual Antam/Pegadaian (harga toko lebih tinggi).
 * Disimpan 5 menit; kalau kedua sumber gagal, harga terakhir yang berhasil dipakai (ditandai basi).
 */
class GoldPrice
{
    private const GRAMS_PER_TROY_OUNCE = 31.1034768;
    private const FRESH = 'gold:fresh';
    private const LAST = 'gold:last';
    private const FAILED = 'gold:failed';

    /** @return array{price_per_gram:?int, as_of:?string, source:?string, stale:bool} */
    public static function current(): array
    {
        if ($fresh = Cache::get(self::FRESH)) return $fresh;

        // saat sumber sedang bermasalah jangan dicoba pada setiap permintaan (supaya halaman tidak lambat)
        if (! Cache::get(self::FAILED)) {
            if ($price = self::fromXaus() ?? self::fromCurrencyApi()) {
                Cache::put(self::FRESH, $price, now()->addMinutes(5));
                Cache::forever(self::LAST, $price);
                return $price;
            }
            Cache::put(self::FAILED, true, now()->addMinute());
        }

        $last = Cache::get(self::LAST);
        return $last ? ['stale' => true] + $last : ['price_per_gram' => null, 'as_of' => null, 'source' => null, 'stale' => true];
    }

    private static function fromXaus(): ?array
    {
        $j = self::get('https://xaus.com/api/v1/spot?currency=IDR');
        if (! $j || data_get($j, 'xau.currency') !== 'IDR') return null;
        return self::price(data_get($j, 'xau.price'), data_get($j, 'price_as_of') ?? data_get($j, 'updated_at'), 'xaus.com', (bool) data_get($j, 'stale', false));
    }

    private static function fromCurrencyApi(): ?array
    {
        $j = self::get('https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/xau.min.json');
        return $j ? self::price(data_get($j, 'xau.idr'), data_get($j, 'date'), 'currency-api (harian)', false) : null;
    }

    private static function get(string $url): ?array
    {
        try {
            $r = Http::timeout(6)->acceptJson()->get($url);
            return $r->successful() && is_array($r->json()) ? $r->json() : null;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    private static function price(mixed $perOunce, ?string $asOf, string $source, bool $stale): ?array
    {
        $gram = is_numeric($perOunce) ? (int) round($perOunce / self::GRAMS_PER_TROY_OUNCE) : 0;
        // penjaga dari data rusak/satuan salah: di luar Rp100 rb – Rp100 jt per gram dianggap tidak sah
        if ($gram < 100_000 || $gram > 100_000_000) return null;
        return ['price_per_gram' => $gram, 'as_of' => $asOf, 'source' => $source, 'stale' => $stale];
    }
}
