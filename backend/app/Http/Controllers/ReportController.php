<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\SavingEntry;
use App\Models\SavingGoal;
use App\Models\Transaction;
use App\Support\Period;
use App\Support\Wallet;
use Illuminate\Http\Request;

/** Ringkasan dan laporan bersifat pribadi. Setoran tabungan mengurangi saldo si penyetor. */
class ReportController extends Controller
{
    private function mine(Request $r)
    {
        return Transaction::where('household_id', $this->hid($r))->where('created_by', $r->user()->id);
    }

    private function myEntries(Request $r)
    {
        return SavingEntry::whereIn('goal_id', SavingGoal::where('household_id', $this->hid($r))->pluck('id'))->where('contributed_by', $r->user()->id);
    }

    public function summary(Request $r)
    {
        [$from, $to] = $this->monthRange($r);
        $all = $this->mine($r)->get(['type', 'amount', 'occurred_at']);
        $saved = (int) $this->myEntries($r)->sum('amount');
        $month = $all->filter(fn ($t) => $t->occurred_at->between($from, $to));
        $key = $this->periodKey($r);
        return [
            'period' => $key, 'label' => Period::label($key, $this->cycleDay($r)),
            'balance' => Wallet::balance($r->user()->id, $this->hid($r)),
            'income' => (int) $month->where('type', 'income')->sum('amount'),
            'expense' => (int) $month->where('type', 'expense')->sum('amount'),
            'saved' => $saved,
        ];
    }

    public function monthly(Request $r)
    {
        [$from, $to] = $this->monthRange($r);
        $tx = $this->mine($r)->whereBetween('occurred_at', [$from, $to])->get();
        $exp = $tx->where('type', 'expense');
        $inc = $tx->where('type', 'income');

        // 4 kotak mingguan dihitung dari tanggal mulai periode; kotak terakhir memuat sisa hari
        $key = $this->periodKey($r);
        [$startLocal] = Period::local($key, $this->cycleDay($r));
        $tz = Period::tz();
        $bucket = fn ($t) => min(3, intdiv((int) $startLocal->copy()->startOfDay()->diffInDays($t->occurred_at->copy()->setTimezone($tz)->startOfDay()), 7));
        $weeks = collect(range(0, 3))->map(fn ($i) => [
            'label' => $startLocal->copy()->addDays($i * 7)->locale('id')->translatedFormat('j M'),
            'income' => (int) $inc->filter(fn ($t) => $bucket($t) === $i)->sum('amount'),
            'expense' => (int) $exp->filter(fn ($t) => $bucket($t) === $i)->sum('amount'),
        ]);

        $cats = Category::where('household_id', $this->hid($r))->get()->keyBy('id');
        $byCat = $exp->groupBy('category_id')->map(fn ($g, $id) => [
            'category_id' => $id, 'name' => $cats[$id]->name ?? 'Lainnya', 'amount' => (int) $g->sum('amount'),
        ])->sortByDesc('amount')->values();

        $total = (int) $exp->sum('amount');
        $income = (int) $inc->sum('amount');

        // Wawasan rule-based
        $prev = $this->mine($r)->where('type', 'expense')
            ->whereBetween('occurred_at', Period::utc(Period::previousKey($key), $this->cycleDay($r)))->get();
        $insights = [];
        $food = $cats->firstWhere('name', 'Makan & Minum');
        if ($food) {
            $now = $exp->where('category_id', $food->id)->sum('amount');
            $was = $prev->where('category_id', $food->id)->sum('amount');
            if ($was > 0 && $now > $was) $insights[] = 'Pengeluaran makan naik '.round(($now - $was) / $was * 100).'% dari bulan lalu.';
        }
        $saved = (int) $this->myEntries($r)->whereBetween('occurred_at', [$from, $to])->where('amount', '>', 0)->where('from_balance', true)->sum('amount');
        if ($income > 0 && $saved > 0) $insights[] = 'Kamu menabung '.round($saved / $income * 100).'% pemasukan bulan ini.';

        return [
            'month' => $key, 'label' => Period::label($key, $this->cycleDay($r)), 'income' => $income, 'expense' => $total,
            'weeks' => $weeks, 'by_category' => $byCat, 'insights' => array_slice($insights, 0, 2),
        ];
    }

    /** Polling ringan: klien cek stempel ini tiap beberapa detik, lalu invalidate cache bila berubah. */
    public function pulse(Request $r)
    {
        $h = $this->hid($r);
        $tx = Transaction::withTrashed()->where('household_id', $h)->where('created_by', $r->user()->id)->max('updated_at');
        $entries = SavingEntry::whereIn('goal_id', SavingGoal::where('household_id', $h)->pluck('id'));
        $last = (clone $entries)->orderByDesc('id')->first(['id', 'contributed_by']);
        return [
            'transactions' => $tx,
            'savings' => (clone $entries)->max('updated_at').'|'.(clone $entries)->count().'|'.SavingGoal::where('household_id', $h)->count(),
            'last_saving_by' => $last?->contributed_by,
            'budgets' => Budget::where('household_id', $h)->where('user_id', $r->user()->id)->max('updated_at'),
        ];
    }
}
