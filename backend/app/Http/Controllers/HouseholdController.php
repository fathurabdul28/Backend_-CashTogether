<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Support\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HouseholdController extends Controller
{
    public function store(Request $r)
    {
        $d = $r->validate(['name' => 'nullable|string|max:60']);
        abort_if($r->user()->householdId(), 409, 'Kamu sudah punya dompet bersama.');

        $h = DB::transaction(function () use ($r, $d) {
            $h = Household::create([
                'name' => $d['name'] ?? 'Dompet Kita', 'created_by' => $r->user()->id,
                'invite_code' => $this->code(), 'invite_expires_at' => now()->addHours(48),
            ]);
            HouseholdMember::create(['household_id' => $h->id, 'user_id' => $r->user()->id, 'role' => 'owner']);
            Seeder::household($h->id, $r->user()->id);
            return $h;
        });
        return $this->show($r->merge([]), $h->id);
    }

    public function join(Request $r)
    {
        $d = $r->validate(['code' => 'required|string|size:6']);
        abort_if($r->user()->householdId(), 409, 'Kamu sudah punya dompet bersama.');

        return DB::transaction(function () use ($r, $d) {
            $h = Household::where('invite_code', strtoupper($d['code']))->lockForUpdate()->first();
            abort_if(! $h || $h->invite_expires_at?->isPast(), 422, 'Kode tidak valid atau sudah kedaluwarsa.');
            abort_if($h->members()->count() >= 2, 422, 'Dompet ini sudah berisi 2 orang.');

            $owner = $h->members()->where('role', 'owner')->with('user')->first();
            HouseholdMember::create([
                'household_id' => $h->id, 'user_id' => $r->user()->id, 'role' => 'partner',
                'color_variant' => $owner->user->gender === $r->user()->gender ? 'alt' : 'default',
            ]);
            Seeder::personalAccount($h->id, $r->user()->id);
            $h->update(['invite_code' => null, 'invite_expires_at' => null]);
            return $this->show($r, $h->id);
        });
    }

    public function showMine(Request $r)
    {
        $id = $r->user()->householdId();
        abort_if(! $id, 404, 'Belum punya dompet.');
        return $this->show($r, $id);
    }

    private function show(Request $r, int $id): array
    {
        $h = Household::with('members.user')->findOrFail($id);
        return [
            'id' => $h->id, 'name' => $h->name,
            'invite_code' => $h->invite_expires_at?->isFuture() ? $h->invite_code : null,
            'invite_expires_at' => $h->invite_expires_at,
            'members' => $h->members->map(fn ($m) => [
                'user_id' => $m->user_id, 'name' => $m->user->name, 'role' => $m->role,
                'gender' => $m->user->identity_override ?? $m->user->gender,
                'color_variant' => $m->color_variant,
            ])->values(),
        ];
    }

    private function code(): string
    {
        do { $c = strtoupper(Str::random(6)); $c = strtr($c, 'O0I1L', 'XYZWV'); }
        while (Household::where('invite_code', $c)->exists());
        return $c;
    }
}
