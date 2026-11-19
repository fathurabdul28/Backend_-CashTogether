<?php

namespace App\Support;

use App\Models\Account;
use App\Models\SavingEntry;
use App\Models\SavingGoal;
use App\Models\Transaction;

class Wallet
{
    /** Saldo pribadi: saldo awal + pemasukan − pengeluaran − setoran tabungan bersih. */
    public static function balance(int $userId, int $householdId): int
    {
        $initial = (int) Account::where('household_id', $householdId)->where('owner_id', $userId)->where('archived', false)->sum('initial_balance');
        $tx = Transaction::where('household_id', $householdId)->where('created_by', $userId);
        $in = (int) (clone $tx)->where('type', 'income')->sum('amount');
        $out = (int) (clone $tx)->where('type', 'expense')->sum('amount');
        $saved = (int) SavingEntry::whereIn('goal_id', SavingGoal::where('household_id', $householdId)->pluck('id'))->where('contributed_by', $userId)->sum('amount');
        return $initial + $in - $out - $saved;
    }
}
