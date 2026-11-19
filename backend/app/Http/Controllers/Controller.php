<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function hid(Request $r): int
    {
        return $r->attributes->get('household_id');
    }

    /** @return array{Carbon, Carbon} awal & akhir bulan dari ?month=YYYY-MM (default bulan ini) */
    protected function monthRange(Request $r): array
    {
        $r->validate(['month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        $start = Carbon::createFromFormat('Y-m-d', ($r->query('month') ?? now()->format('Y-m')).'-01')->startOfDay();
        return [$start, $start->copy()->endOfMonth()];
    }
}
