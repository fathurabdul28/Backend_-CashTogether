<?php

namespace App\Services;

use App\Models\SavingEntry;
use App\Models\SavingGoal;
use App\Models\Transaction;

/**
 * Menjaga setoran tabungan selalu sama dengan transaksi pengeluarannya:
 * transaksi diubah → setoran ikut berubah; dihapus/BATAL → setoran hilang; dipulihkan → setoran kembali.
 * Saldo hanya terpotong sekali (oleh transaksinya), lihat Wallet::balance().
 */
class SavingLink
{
    public static function sync(Transaction $tx): void
    {
        $entry = SavingEntry::where('transaction_id', $tx->id)->first();
        $goalId = $tx->saving_goal_id ?? $entry?->goal_id;
        if (! $goalId) return;

        if ($tx->trashed() || $tx->type !== 'expense' || ! $tx->saving_goal_id) {
            $entry?->delete();
        } else {
            SavingEntry::updateOrCreate(['transaction_id' => $tx->id], [
                'goal_id' => $tx->saving_goal_id, 'amount' => $tx->amount, 'contributed_by' => $tx->created_by,
                'occurred_at' => $tx->occurred_at, 'note' => $tx->note === null ? null : mb_substr($tx->note, 0, 200),
            ]);
        }

        if ($goal = SavingGoal::find($goalId)) {
            $total = (int) SavingEntry::where('goal_id', $goal->id)->sum('amount');
            $goal->update(['achieved_at' => $goal->target_amount > 0 && $total >= $goal->target_amount ? ($goal->achieved_at ?? now()) : null]);
        }
    }
}
