<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\Request;

/** Akun bersifat pribadi: tiap orang hanya melihat akunnya sendiri. */
class AccountController extends Controller
{
    public function index(Request $r)
    {
        $accounts = $this->mine($r)->where('archived', false)->get();
        $sums = Transaction::where('household_id', $this->hid($r))->whereIn('account_id', $accounts->pluck('id'))
            ->get(['type', 'amount', 'account_id'])->groupBy('account_id');
        return $accounts->map(function ($a) use ($sums) {
            $t = $sums->get($a->id, collect());
            $bal = $a->initial_balance + $t->where('type', 'income')->sum('amount') - $t->where('type', 'expense')->sum('amount');
            return $a->toArray() + ['balance' => (int) $bal];
        });
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'name' => 'required|string|max:40', 'type' => 'required|in:cash,bank,ewallet,credit', 'initial_balance' => 'integer',
        ]);
        return response(Account::create($d + ['household_id' => $this->hid($r), 'owner_id' => $r->user()->id]), 201);
    }

    public function update(Request $r, int $id)
    {
        $a = $this->mine($r)->findOrFail($id);
        $a->update($r->validate(['name' => 'sometimes|string|max:40', 'archived' => 'sometimes|boolean']));
        return $a;
    }

    private function mine(Request $r)
    {
        return Account::where('household_id', $this->hid($r))->where('owner_id', $r->user()->id);
    }
}
