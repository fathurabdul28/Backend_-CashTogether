<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Periode keuangan yang bisa mengikuti tanggal gajian.
 * Periode dinamai menurut BULAN AWALNYA: "2026-10" dengan tanggal mulai 25 = 25 Okt 2026 s.d. 24 Nov 2026.
 * Batas dihitung dalam zona waktu tampilan (WIB), lalu diubah ke UTC untuk query.
 */
class Period
{
    public static function tz(): string
    {
        return config('app.display_timezone', 'Asia/Jakarta');
    }

    public static function clampDay(?int $day): int
    {
        return max(1, min(28, (int) ($day ?: 1)));
    }

    /** Kunci periode (YYYY-MM = bulan awal) yang sedang berjalan pada $now. */
    public static function currentKey(int $cycleDay, ?Carbon $now = null): string
    {
        $now = ($now ?? Carbon::now())->copy()->setTimezone(self::tz());
        $start = $now->day >= $cycleDay ? $now->copy()->startOfMonth() : $now->copy()->startOfMonth()->subMonthNoOverflow();
        return $start->format('Y-m');
    }

    public static function previousKey(string $key): string
    {
        return Carbon::createFromFormat('Y-m-d', $key.'-01', self::tz())->subMonthNoOverflow()->format('Y-m');
    }

    /** @return array{Carbon, Carbon} awal dan akhir periode dalam zona waktu tampilan (akhir = 23:59:59 hari terakhir) */
    public static function local(string $key, int $cycleDay): array
    {
        $start = Carbon::createFromFormat('Y-m-d H:i:s', sprintf('%s-%02d 00:00:00', $key, self::clampDay($cycleDay)), self::tz());
        return [$start, $start->copy()->addMonthNoOverflow()->subSecond()];
    }

    /** @return array{Carbon, Carbon} sama seperti local() tetapi dalam UTC, siap dipakai di query */
    public static function utc(string $key, int $cycleDay): array
    {
        [$s, $e] = self::local($key, $cycleDay);
        return [$s->copy()->utc(), $e->copy()->utc()];
    }

    /** Label singkat, mis. "25 Okt – 24 Nov 2026"; bulan kalender biasa: "November 2026". */
    public static function label(string $key, int $cycleDay): string
    {
        [$s, $e] = self::local($key, $cycleDay);
        if ($cycleDay <= 1) return $s->copy()->locale('id')->translatedFormat('F Y');
        return $s->copy()->locale('id')->translatedFormat('j M').' – '.$e->copy()->locale('id')->translatedFormat('j M Y');
    }
}
