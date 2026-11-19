<?php

namespace App\Http\Controllers;

use App\Support\Period;
use Carbon\Carbon;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function hid(Request $r): int
    {
        return $r->attributes->get('household_id');
    }

    protected function cycleDay(Request $r): int
    {
        return Period::clampDay($r->user()->cycle_start_day);
    }

    /** Kunci periode dari ?month=YYYY-MM (bulan AWAL periode); default: periode yang sedang berjalan. */
    protected function periodKey(Request $r): string
    {
        $r->validate(['month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        return $r->query('month') ?? Period::currentKey($this->cycleDay($r));
    }

    /** @return array{Carbon, Carbon} awal & akhir periode dalam UTC, untuk query ke database */
    protected function monthRange(Request $r): array
    {
        return Period::utc($this->periodKey($r), $this->cycleDay($r));
    }
}
