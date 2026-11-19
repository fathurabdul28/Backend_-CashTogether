<?php

namespace App\Services;

use App\Models\RecurringIncome;
use App\Models\Transaction;
use App\Support\Period;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Mencatat pemasukan rutin yang sudah jatuh tempo. Dipanggil "malas" (saat pengguna membuka app/bot),
 * karena server gratis bisa tertidur sehingga penjadwal harian tidak bisa diandalkan.
 * Aman dipanggil berulang: unik per (aturan, bulan).
 */
class RecurringIncomes
{
    public static function apply(int $userId, int $householdId, ?Carbon $now = null): int
    {
        $now = ($now ?? Carbon::now())->copy()->setTimezone(Period::tz());
        $made = 0;

        foreach (RecurringIncome::where('user_id', $userId)->where('household_id', $householdId)->where('active', true)->get() as $rule) {
            $guard = 0;
            while ($guard++ < 60) { // batas pengaman: maks 5 tahun tertinggal sekali jalan
                $at = Carbon::createFromFormat('Y-m-d H:i:s', $rule->next_month.'-'.sprintf('%02d', $rule->day).' 08:00:00', Period::tz());
                if ($at->gt($now)) break;

                try {
                    Transaction::create([
                        'household_id' => $householdId, 'created_by' => $userId, 'paid_by' => $userId,
                        'type' => 'income', 'amount' => $rule->amount, 'category_id' => $rule->category_id,
                        'account_id' => $rule->account_id, 'occurred_at' => $at->copy()->utc(), 'note' => $rule->note,
                        'source' => 'recurring', 'recurring_id' => $rule->id, 'recurring_month' => $rule->next_month,
                    ]);
                    $made++;
                } catch (UniqueConstraintViolationException) {
                    // sudah dibuat oleh permintaan lain yang berjalan bersamaan
                }
                $rule->next_month = Carbon::createFromFormat('Y-m-d', $rule->next_month.'-01')->addMonthNoOverflow()->format('Y-m');
            }
            $rule->save();
        }
        return $made;
    }
}
