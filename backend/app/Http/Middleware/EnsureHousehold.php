<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Memastikan user sudah punya dompet bersama; menaruh household_id di request. */
class EnsureHousehold
{
    public function handle(Request $request, Closure $next)
    {
        $id = $request->user()->householdId();
        abort_if(! $id, 409, 'Kamu belum punya dompet bersama.');
        $request->attributes->set('household_id', $id);
        \App\Services\RecurringIncomes::apply($request->user()->id, $id); // catat gaji rutin yang sudah jatuh tempo
        return $next($request);
    }
}
