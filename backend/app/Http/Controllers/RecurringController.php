<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringIncome;
use App\Services\RecurringIncomes;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Pemasukan rutin (gaji bulanan) — pribadi per pengguna. */
class RecurringController extends Controller
{
    public function index(Request $r)
    {
        return $this->mine($r)->orderByDesc('id')->get();
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'amount' => 'required|integer|min:1|max:999999999999999',
            'category_id' => 'required|integer',
            'account_id' => 'required|integer',
            'day' => 'required|integer|between:1,28',
            'note' => 'nullable|string|max:500',
            'start_month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'], // bulan pertama yang dibuatkan otomatis
        ]);
        $this->check($r, $d);
        $rule = RecurringIncome::create([
            'household_id' => $this->hid($r), 'user_id' => $r->user()->id,
            'amount' => $d['amount'], 'category_id' => $d['category_id'], 'account_id' => $d['account_id'],
            'day' => $d['day'], 'note' => $d['note'] ?? null,
            'next_month' => $d['start_month'] ?? now(\App\Support\Period::tz())->addMonthNoOverflow()->format('Y-m'),
        ]);
        RecurringIncomes::apply($r->user()->id, $this->hid($r));
        return response($rule->fresh(), 201);
    }

    public function update(Request $r, int $id)
    {
        $rule = $this->mine($r)->findOrFail($id);
        $d = $r->validate([
            'amount' => 'sometimes|integer|min:1|max:999999999999999',
            'day' => 'sometimes|integer|between:1,28',
            'note' => 'sometimes|nullable|string|max:500',
            'active' => 'sometimes|boolean',
            'category_id' => 'sometimes|integer',
            'account_id' => 'sometimes|integer',
        ]);
        $this->check($r, $d);
        if (($d['active'] ?? false) && ! $rule->active) { // dinyalakan lagi: lewati bulan-bulan saat berhenti
            $now = now(\App\Support\Period::tz());
            $day = $d['day'] ?? $rule->day;
            $from = $now->day >= $day ? $now->copy()->startOfMonth()->addMonthNoOverflow() : $now->copy()->startOfMonth();
            $d['next_month'] = max($rule->next_month, $from->format('Y-m'));
        }
        $rule->update($d); // hanya berlaku untuk bulan berikutnya; transaksi yang sudah tercatat tidak berubah
        RecurringIncomes::apply($r->user()->id, $this->hid($r));
        return $rule->fresh();
    }

    public function destroy(Request $r, int $id)
    {
        $this->mine($r)->findOrFail($id)->delete(); // transaksi yang sudah tercatat tetap ada
        return response()->noContent();
    }

    private function mine(Request $r)
    {
        return RecurringIncome::where('household_id', $this->hid($r))->where('user_id', $r->user()->id);
    }

    private function check(Request $r, array $d): void
    {
        $h = $this->hid($r);
        if (isset($d['account_id']) && ! Account::where('household_id', $h)->where('owner_id', $r->user()->id)->whereKey($d['account_id'])->exists()) {
            throw ValidationException::withMessages(['account_id' => 'Akun tidak ditemukan.']);
        }
        if (isset($d['category_id']) && ! Category::where('household_id', $h)->where('kind', 'income')->whereKey($d['category_id'])->exists()) {
            throw ValidationException::withMessages(['category_id' => 'Kategori pemasukan tidak ditemukan.']);
        }
    }
}
