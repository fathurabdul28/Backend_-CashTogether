<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\Period;
use Illuminate\Http\Request;

/**
 * Anggaran pribadi, dihitung dari pengeluaran pemiliknya sendiri.
 * Kolom `month` = tanggal 1 dari bulan AWAL periode, sehingga tidak berubah walau tanggal mulai periode diganti.
 */
class BudgetController extends Controller
{
    public function index(Request $r)
    {
        $key = $this->periodKey($r);
        [$from, $to] = Period::utc($key, $this->cycleDay($r));
        $spent = Transaction::where('household_id', $this->hid($r))->where('created_by', $r->user()->id)->where('type', 'expense')
            ->whereBetween('occurred_at', [$from, $to])->get()->groupBy('category_id')
            ->map(fn ($g) => (int) $g->sum('amount'));
        $rows = $this->mine($r)->where('month', $key.'-01')->get()
            ->map(fn ($b) => $b->toArray() + ['spent' => $spent->get($b->category_id, 0)]);
        return [
            'month' => $key,
            'total_limit' => (int) $rows->sum('limit_amount'),
            'total_spent' => (int) $rows->sum('spent'),
            'items' => $rows->values(),
        ];
    }

    public function upsert(Request $r)
    {
        $d = $r->validate(['category_id' => 'required|integer', 'limit_amount' => 'required|integer|min:0']);
        $key = $this->periodKey($r);
        abort_unless(Category::where('household_id', $this->hid($r))->where('kind', 'expense')->whereKey($d['category_id'])->exists(), 422, 'Kategori tidak valid.');
        return Budget::updateOrCreate(
            ['household_id' => $this->hid($r), 'user_id' => $r->user()->id, 'category_id' => $d['category_id'], 'month' => $key.'-01'],
            ['limit_amount' => $d['limit_amount']],
        );
    }

    public function copyPrevious(Request $r)
    {
        $key = $this->periodKey($r);
        $n = 0;
        foreach ($this->mine($r)->where('month', Period::previousKey($key).'-01')->get() as $b) {
            $n += Budget::firstOrCreate(
                ['household_id' => $b->household_id, 'user_id' => $b->user_id, 'category_id' => $b->category_id, 'month' => $key.'-01'],
                ['limit_amount' => $b->limit_amount],
            )->wasRecentlyCreated ? 1 : 0;
        }
        return ['copied' => $n];
    }

    private function mine(Request $r)
    {
        return Budget::where('household_id', $this->hid($r))->where('user_id', $r->user()->id);
    }
}
