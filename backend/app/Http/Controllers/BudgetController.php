<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;

/** Anggaran pribadi, dihitung dari pengeluaran pemiliknya sendiri. */
class BudgetController extends Controller
{
    public function index(Request $r)
    {
        [$from, $to] = $this->monthRange($r);
        $spent = Transaction::where('household_id', $this->hid($r))->where('created_by', $r->user()->id)->where('type', 'expense')
            ->whereBetween('occurred_at', [$from, $to])->get()->groupBy('category_id')
            ->map(fn ($g) => (int) $g->sum('amount'));
        $rows = $this->mine($r)->where('month', $from->toDateString())->get()
            ->map(fn ($b) => $b->toArray() + ['spent' => $spent->get($b->category_id, 0)]);
        return [
            'month' => $from->format('Y-m'),
            'total_limit' => (int) $rows->sum('limit_amount'),
            'total_spent' => (int) $rows->sum('spent'),
            'items' => $rows->values(),
        ];
    }

    public function upsert(Request $r)
    {
        $d = $r->validate(['category_id' => 'required|integer', 'limit_amount' => 'required|integer|min:0']);
        [$from] = $this->monthRange($r);
        abort_unless(Category::where('household_id', $this->hid($r))->where('kind', 'expense')->whereKey($d['category_id'])->exists(), 422, 'Kategori tidak valid.');
        return Budget::updateOrCreate(
            ['household_id' => $this->hid($r), 'user_id' => $r->user()->id, 'category_id' => $d['category_id'], 'month' => $from->toDateString()],
            ['limit_amount' => $d['limit_amount']],
        );
    }

    public function copyPrevious(Request $r)
    {
        [$from] = $this->monthRange($r);
        $n = 0;
        foreach ($this->mine($r)->where('month', $from->copy()->subMonth()->toDateString())->get() as $b) {
            $n += Budget::firstOrCreate(
                ['household_id' => $b->household_id, 'user_id' => $b->user_id, 'category_id' => $b->category_id, 'month' => $from->toDateString()],
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
