<?php

namespace App\Http\Controllers;

use App\Models\SavingEntry;
use App\Models\SavingGoal;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SavingController extends Controller
{
    public function index(Request $r)
    {
        $goals = SavingGoal::where('household_id', $this->hid($r))->get();
        $entries = SavingEntry::whereIn('goal_id', $goals->pluck('id'))->get()->groupBy('goal_id');
        return $goals->map(fn ($g) => $this->withTotals($g, $entries->get($g->id, collect())));
    }

    public function show(Request $r, int $id)
    {
        $g = $this->find($r, $id);
        $entries = SavingEntry::where('goal_id', $g->id)->orderByDesc('occurred_at')->get();
        return $this->withTotals($g, $entries) + ['entries' => $entries];
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'name' => 'required|string|max:60', 'target_amount' => 'required|integer|min:1',
            'target_date' => 'nullable|date|after:today', 'icon' => 'required|string|max:40',
            'color' => 'required|in:sage,clay,teal,mustard,plum,stone', 'account_id' => 'nullable|integer',
        ]);
        return response(SavingGoal::create($d + ['household_id' => $this->hid($r), 'created_by' => $r->user()->id]), 201);
    }

    public function destroy(Request $r, int $id)
    {
        $goal = $this->find($r, $id);
        DB::transaction(function () use ($goal) {
            // pengeluaran "nabung" ikut dihapus, sama seperti setoran biasa: uangnya kembali ke saldo
            Transaction::where('household_id', $goal->household_id)->where('saving_goal_id', $goal->id)->get()->each->delete();
            $goal->delete();
        });
        return response()->noContent();
    }

    /** Setoran (amount > 0) atau penarikan (amount < 0) */
    public function addEntry(Request $r, int $id)
    {
        $d = $r->validate(['amount' => 'required|integer|not_in:0', 'note' => 'nullable|string|max:200']);
        return DB::transaction(function () use ($r, $id, $d) {
            $g = SavingGoal::where('household_id', $this->hid($r))->lockForUpdate()->findOrFail($id);
            $total = (int) SavingEntry::where('goal_id', $g->id)->sum('amount');
            abort_if($total + $d['amount'] < 0, 422, 'Saldo tabungan tidak cukup.');
            $e = SavingEntry::create($d + ['goal_id' => $g->id, 'contributed_by' => $r->user()->id]);
            $g->update(['achieved_at' => $g->target_amount > 0 && $total + $d['amount'] >= $g->target_amount ? ($g->achieved_at ?? now()) : null]);
            return response($e, 201);
        });
    }

    /** Tukar sebagian tabungan rupiah menjadi emas (mis. Rp2.000.000 → 1 gram). */
    public function convert(Request $r, int $id)
    {
        $d = $r->validate(['amount' => 'required|integer|min:1', 'gold_grams' => 'required|numeric|min:0.01|max:100000']);
        return DB::transaction(function () use ($r, $id, $d) {
            $g = SavingGoal::where('household_id', $this->hid($r))->lockForUpdate()->findOrFail($id);
            $total = (int) SavingEntry::where('goal_id', $g->id)->sum('amount');
            abort_if($d['amount'] > $total, 422, 'Rupiah di tabungan ini tidak cukup.');
            $e = SavingEntry::create([
                'goal_id' => $g->id, 'amount' => -$d['amount'], 'gold_grams' => round($d['gold_grams'], 2),
                'from_balance' => false, 'contributed_by' => $r->user()->id, 'note' => 'Konversi ke emas',
            ]);
            $this->refreshAchieved($g);
            return response($e, 201);
        });
    }

    /** Membatalkan konversi (salah ketik): rupiah kembali, gramnya hilang. */
    public function undoConversion(Request $r, int $id, int $entry)
    {
        $g = $this->find($r, $id);
        $e = SavingEntry::where('goal_id', $g->id)->findOrFail($entry);
        abort_unless($e->is_conversion, 422, 'Hanya konversi emas yang bisa dibatalkan di sini.');
        $e->delete();
        $this->refreshAchieved($g);
        return response()->noContent();
    }

    private function refreshAchieved(SavingGoal $g): void
    {
        $total = (int) SavingEntry::where('goal_id', $g->id)->sum('amount');
        $g->update(['achieved_at' => $g->target_amount > 0 && $total >= $g->target_amount ? ($g->achieved_at ?? now()) : null]);
    }

    private function find(Request $r, int $id): SavingGoal
    {
        return SavingGoal::where('household_id', $this->hid($r))->findOrFail($id);
    }

    private function withTotals(SavingGoal $g, $entries): array
    {
        $saved = (int) $entries->sum('amount');
        $by = $entries->reject->is_conversion->groupBy('contributed_by')->map(fn ($e) => (int) $e->sum('amount'));
        $monthly = null;
        if ($g->target_date && $saved < $g->target_amount) {
            $months = max(1, (int) ceil(now()->diffInDays($g->target_date, false) / 30));
            $monthly = (int) ceil(($g->target_amount - $saved) / $months);
        }
        return $g->toArray() + ['saved' => $saved, 'gold_grams' => round((float) $entries->sum('gold_grams'), 2), 'contributions' => $by, 'monthly_needed' => $monthly];
    }
}
