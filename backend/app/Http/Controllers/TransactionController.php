<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringIncome;
use App\Models\Transaction;
use App\Services\RecurringIncomes;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Transaksi bersifat pribadi: hanya pembuatnya yang bisa melihat dan mengubah. */
class TransactionController extends Controller
{
    public function index(Request $r)
    {
        [$from, $to] = $this->monthRange($r);
        return $this->mine($r)
            ->whereBetween('occurred_at', [$from, $to])
            ->when($r->query('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($r->query('category_id'), fn ($q, $v) => $q->where('category_id', $v))
            ->when($r->query('q'), fn ($q, $v) => $q->where('note', 'like', '%'.$v.'%'))
            ->orderByDesc('occurred_at')->orderByDesc('id')->get();
    }

    public function show(Request $r, int $id) { return $this->find($r, $id); }

    public function store(Request $r)
    {
        $d = $this->validated($r);
        $d += ['household_id' => $this->hid($r), 'created_by' => $r->user()->id, 'paid_by' => $r->user()->id];
        $d['occurred_at'] ??= now();
        $repeat = $r->boolean('repeat_monthly') && $d['type'] === 'income';
        $tx = Transaction::create($d);
        if ($repeat) { // "gaji tiap bulan": bulan berikutnya dicatat otomatis pada tanggal yang sama
            $local = $tx->occurred_at->copy()->setTimezone(Period::tz());
            RecurringIncome::create([
                'household_id' => $d['household_id'], 'user_id' => $r->user()->id, 'amount' => $d['amount'],
                'category_id' => $d['category_id'], 'account_id' => $d['account_id'], 'note' => $d['note'] ?? null,
                'day' => min(28, $local->day), 'next_month' => $local->copy()->startOfMonth()->addMonthNoOverflow()->format('Y-m'),
            ]);
            RecurringIncomes::apply($r->user()->id, $d['household_id']);
        }
        return response($tx, 201);
    }

    public function update(Request $r, int $id)
    {
        $tx = $this->find($r, $id);
        $tx->update($this->validated($r, true, $tx->type) + ['updated_by' => $r->user()->id]);
        return $tx->fresh();
    }

    public function destroy(Request $r, int $id)
    {
        $this->find($r, $id)->delete(); // soft delete
        return response()->noContent();
    }

    public function restore(Request $r, int $id)
    {
        $tx = Transaction::onlyTrashed()->where('household_id', $this->hid($r))->where('created_by', $r->user()->id)->findOrFail($id);
        $tx->restore();
        return $tx;
    }

    private function mine(Request $r)
    {
        return Transaction::where('household_id', $this->hid($r))->where('created_by', $r->user()->id);
    }

    private function find(Request $r, int $id): Transaction
    {
        return $this->mine($r)->findOrFail($id);
    }

    private function validated(Request $r, bool $partial = false, ?string $existingType = null): array
    {
        $req = $partial ? 'sometimes|' : 'required|';
        $d = $r->validate([
            'type' => $req.'in:income,expense',
            'amount' => $req.'integer|min:1|max:999999999999999',
            'category_id' => $req.'integer',
            'account_id' => $req.'integer',
            'occurred_at' => 'nullable|date|before_or_equal:+1 day',
            'note' => 'nullable|string|max:500',
            'repeat_monthly' => 'sometimes|boolean',
        ]);
        unset($d['repeat_monthly']);
        $h = $this->hid($r);
        $fail = fn ($f, $m) => throw ValidationException::withMessages([$f => $m]);

        if (! empty($d['account_id']) && ! Account::where('household_id', $h)->where('owner_id', $r->user()->id)->whereKey($d['account_id'])->exists()) {
            $fail('account_id', 'Akun tidak ditemukan.');
        }
        $type = $d['type'] ?? $existingType;
        if (! empty($d['category_id'])) {
            $cat = Category::where('household_id', $h)->find($d['category_id']);
            if (! $cat || ($type && $cat->kind !== $type)) $fail('category_id', 'Kategori tidak cocok.');
        }
        return $d;
    }
}
