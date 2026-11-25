<?php

namespace App\Services\WhatsApp;

use Carbon\Carbon;

/**
 * Daftar tabungan banyak baris, contoh:
 *   Seserahan:
 *   1. Jam + dompet : Rp 153.000
 *   2. cincin emas : 1,5 Gram
 *   Tabungan Rumah
 *   25 Oktober 2026 : Rp. 2.463.000 + 2 Gram Logam Mulia
 * Judul = baris pendek diawali "tabungan", "Seserahan", atau nama tabungan yang sudah ada.
 */
class SavingListParser
{
    private const MONTHS = ['januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12];

    /**
     * @param  list<string>  $goalNames
     * @return list<array{name:string, items:list<array{name:string, amount:int, grams:?float, date:?Carbon}>}> kosong bila bukan daftar tabungan
     */
    public function parse(string $text, array $goalNames = []): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text)), fn ($l) => $l !== ''));
        if (count($lines) < 2) return [];

        $known = array_map(fn ($n) => self::key($n), $goalNames);
        $sections = [];
        $current = null;
        foreach ($lines as $line) {
            if ($header = $this->header($line, $known)) {
                $current = self::key($header);
                $sections[$current] ??= ['name' => $header, 'items' => []];
            } elseif ($current !== null && ($item = $this->item($line))) {
                $sections[$current]['items'][] = $item;
            }
        }

        // hanya bagian yang benar-benar berisi nominal atau gram
        return array_values(array_filter($sections, fn ($s) => collect($s['items'])->contains(fn ($i) => $i['amount'] > 0 || $i['grams'])));
    }

    /** Kunci pembanding nama tabungan: kata "tabungan" diabaikan. */
    public static function key(string $name): string
    {
        $words = array_diff(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($name), -1, PREG_SPLIT_NO_EMPTY), ['tabungan']);
        return implode(' ', $words);
    }

    private function header(string $line, array $known): ?string
    {
        $name = trim(rtrim($line, " :"));
        if ($name === '' || preg_match('/[\d:]/u', $name) || count(preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY)) > 4) return null;
        $lower = mb_strtolower($name);
        $is = preg_match('/^tabungan\s+\S/u', $lower) || $lower === 'seserahan' || in_array(self::key($name), $known, true);
        return $is ? mb_substr(mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1), 0, 60) : null;
    }

    /** @return array{name:string, amount:int, grams:?float, date:?Carbon}|null */
    private function item(string $line): ?array
    {
        $line = trim(preg_replace('/^(?:\d{1,2}\s*[.)]|[-•*])\s*/u', '', $line));
        if ($line === '') return null;

        [$name, $value] = str_contains($line, ':') ? array_map('trim', explode(':', $line, 2)) : [$line, null];
        $rest = $value ?? $line;

        $grams = null;
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:gram|gr)(?![\p{L}])(?:\s*(?:logam mulia|emas|lm)(?![\p{L}]))?/iu', $rest, $m)) {
            $grams = (float) str_replace(',', '.', $m[1]);
            $rest = str_replace($m[0], ' ', $rest);
        }

        // Rp 153.000 | Rp. 2.463.000 | 1 juta | 200rb | 200000
        $amount = 0;
        $re = '/(?:rp\.?\s*(\d+(?:[.,]\d+)*)\s*(juta|jt|ribu|rb|k)?|(\d+(?:[.,]\d+)?)\s*(juta|jt|ribu|rb|k)|(\d{1,3}(?:\.\d{3})+|\d{4,}))(?![\p{L}\p{N}])/iu';
        if (preg_match($re, $rest, $m)) {
            $number = $m[1] ?: ($m[3] ?? '') ?: ($m[5] ?? '');
            $unit = mb_strtolower(($m[2] ?? '') ?: ($m[4] ?? ''));
            $mult = ['juta' => 1_000_000, 'jt' => 1_000_000, 'ribu' => 1_000, 'rb' => 1_000, 'k' => 1_000][$unit] ?? null;
            $amount = $mult ? (int) round((float) str_replace(',', '.', $number) * $mult) : (int) preg_replace('/\D/', '', $number);
            $rest = str_replace($m[0], ' ', $rest);
        }
        if ($amount > 999_999_999_999) return null;
        if ($value === null) $name = trim($rest, " \t+-");

        if ($name === '' || mb_strtolower($name) === 'total') return null;

        $date = null;
        if (preg_match('/^(\d{1,2})\s+('.implode('|', array_keys(self::MONTHS)).')\s+(\d{4})$/iu', $name, $m)
            && checkdate(self::MONTHS[mb_strtolower($m[2])], (int) $m[1], (int) $m[3])) {
            $date = Carbon::create((int) $m[3], self::MONTHS[mb_strtolower($m[2])], (int) $m[1], 12, 0, 0, \App\Support\Period::tz());
        }

        return ['name' => mb_substr($name, 0, 200), 'amount' => $amount, 'grams' => $grams, 'date' => $date];
    }
}
