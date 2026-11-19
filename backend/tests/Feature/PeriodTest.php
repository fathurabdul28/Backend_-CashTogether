<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Period;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

afterEach(fn () => Carbon::setTestNow());

function periodUser(int $cycle = 25): User
{
    $u = User::create(['name' => 'Wulan', 'email' => 'p@x.id', 'password' => 'rahasia123', 'gender' => 'female']);
    Sanctum::actingAs($u);
    test()->postJson('/api/household')->assertOk();
    $u->forceFill(['cycle_start_day' => $cycle])->save();
    return $u->fresh();
}

function spend(User $u, int $amount, string $at, string $type = 'expense'): void
{
    $hid = $u->householdId();
    Transaction::create([
        'household_id' => $hid, 'type' => $type, 'amount' => $amount, 'occurred_at' => Carbon::parse($at)->utc(),
        'category_id' => Category::where('household_id', $hid)->where('kind', $type)->value('id'),
        'account_id' => Account::where('owner_id', $u->id)->value('id'), 'created_by' => $u->id, 'paid_by' => $u->id,
    ]);
}

it('menghitung batas periode dalam WIB dan nama periodenya', function () {
    [$s, $e] = Period::local('2026-10', 25);
    expect($s->format('Y-m-d H:i T'))->toBe('2026-10-25 00:00 WIB')
        ->and($e->format('Y-m-d H:i:s'))->toBe('2026-11-24 23:59:59');
    [$us] = Period::utc('2026-10', 25);
    expect($us->format('Y-m-d H:i'))->toBe('2026-10-24 17:00'); // 00:00 WIB = 17:00 UTC hari sebelumnya
    expect(Period::label('2026-10', 25))->toBe('25 Okt – 24 Nov 2026')
        ->and(Period::label('2026-11', 1))->toBe('November 2026')
        ->and(Period::previousKey('2026-01'))->toBe('2025-12');
    expect(Period::clampDay(0))->toBe(1)->and(Period::clampDay(40))->toBe(28);
});

it('periode yang sedang berjalan mengikuti tanggal gajian', function () {
    $now = Carbon::parse('2026-11-19 10:00', 'Asia/Jakarta');
    expect(Period::currentKey(25, $now))->toBe('2026-10')   // belum gajian bulan ini
        ->and(Period::currentKey(1, $now))->toBe('2026-11')
        ->and(Period::currentKey(19, $now))->toBe('2026-11') // tepat di tanggal mulai
        ->and(Period::currentKey(20, $now))->toBe('2026-10');
    expect(Period::currentKey(25, Carbon::parse('2026-01-10', 'Asia/Jakarta')))->toBe('2025-12'); // lintas tahun
});

it('tanggal mulai periode divalidasi 1 sampai 28', function () {
    periodUser(1);
    $this->patchJson('/api/me', ['cycle_start_day' => 0])->assertStatus(422);
    $this->patchJson('/api/me', ['cycle_start_day' => 29])->assertStatus(422);
    $this->patchJson('/api/me', ['cycle_start_day' => 25])->assertOk()->assertJsonPath('cycle_start_day', 25);
    $this->getJson('/api/me')->assertJsonPath('cycle_start_day', 25);
});

it('transaksi dibagi ke periode dengan benar, termasuk dini hari tanggal 25 WIB', function () {
    $u = periodUser(25);
    spend($u, 10_000, '2026-10-24T23:30:00+07:00'); // periode 2026-09
    spend($u, 20_000, '2026-10-25T00:30:00+07:00'); // periode 2026-10 (UTC-nya masih tanggal 24!)
    spend($u, 40_000, '2026-11-24T23:30:00+07:00'); // periode 2026-10
    spend($u, 80_000, '2026-11-25T00:30:00+07:00'); // periode 2026-11
    spend($u, 5_000_000, '2026-10-25T09:00:00+07:00', 'income');

    $this->getJson('/api/summary?month=2026-09')->assertJson(['expense' => 10_000, 'income' => 0]);
    $this->getJson('/api/summary?month=2026-10')->assertJson(['expense' => 60_000, 'income' => 5_000_000, 'label' => '25 Okt – 24 Nov 2026']);
    $this->getJson('/api/summary?month=2026-11')->assertJson(['expense' => 80_000]);
    expect($this->getJson('/api/transactions?month=2026-10')->json())->toHaveCount(3); // 2 pengeluaran + 1 pemasukan
    $this->getJson('/api/summary?month=2026-10')->assertJsonPath('balance', 5_000_000 - 150_000); // saldo total tidak terpengaruh periode
});

it('tanpa parameter month, memakai periode yang sedang berjalan', function () {
    $u = periodUser(25);
    Carbon::setTestNow(Carbon::parse('2026-11-19 10:00', 'Asia/Jakarta'));
    spend($u, 30_000, '2026-11-10T12:00:00+07:00'); // masih periode yang dimulai 25 Okt
    $this->getJson('/api/summary')->assertJson(['period' => '2026-10', 'expense' => 30_000]);
    $this->patchJson('/api/me', ['cycle_start_day' => 1])->assertOk(); // lewat API, bukan objek lama
    $this->getJson('/api/summary')->assertJson(['period' => '2026-11', 'expense' => 30_000]);
});

it('grafik mingguan dihitung dari tanggal mulai periode', function () {
    $u = periodUser(25);
    spend($u, 1_000, '2026-10-25T12:00:00+07:00');  // minggu 1
    spend($u, 2_000, '2026-11-02T12:00:00+07:00');  // minggu 2 (hari ke-9)
    spend($u, 4_000, '2026-11-24T12:00:00+07:00');  // minggu 4 (sisa hari)
    $r = $this->getJson('/api/reports/monthly?month=2026-10')->assertOk();
    expect(collect($r->json('weeks'))->pluck('label')->all())->toBe(['25 Okt', '1 Nov', '8 Nov', '15 Nov'])
        ->and(collect($r->json('weeks'))->pluck('expense')->all())->toBe([1_000, 2_000, 0, 4_000]);
    $r->assertJsonPath('label', '25 Okt – 24 Nov 2026');
});

it('anggaran melekat pada periode walau tanggal mulai diganti', function () {
    periodUser(25);
    $cat = Category::where('kind', 'expense')->value('id');
    $this->putJson('/api/budgets?month=2026-10', ['category_id' => $cat, 'limit_amount' => 100_000])->assertSuccessful();
    expect(DB::table('budgets')->value('month'))->toBe('2026-10-01');
    $this->patchJson('/api/me', ['cycle_start_day' => 20])->assertOk();
    $this->getJson('/api/budgets?month=2026-10')->assertJson(['total_limit' => 100_000]);
    $this->postJson('/api/budgets/copy-previous?month=2026-11')->assertOk()->assertJson(['copied' => 1]); // Okt -> Nov
    $this->postJson('/api/budgets/copy-previous?month=2026-11')->assertOk()->assertJson(['copied' => 0]); // tidak digandakan
});

it('menolak tanggal transaksi yang jauh di masa depan, menerima tanggal lampau', function () {
    $u = periodUser(1);
    $body = fn ($at) => ['type' => 'expense', 'amount' => 1000, 'category_id' => Category::where('kind', 'expense')->value('id'),
        'account_id' => Account::where('owner_id', $u->id)->value('id'), 'occurred_at' => $at];
    $this->postJson('/api/transactions', $body(now()->addDays(10)->toIso8601String()))->assertStatus(422);
    $this->postJson('/api/transactions', $body(now()->subDays(40)->toIso8601String()))->assertCreated();
});

it('REKAP di WhatsApp menyebut periode gajian', function () {
    config(['services.fonnte.webhook_secret' => 's', 'services.fonnte.token' => 't']);
    Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);
    $u = periodUser(25);
    $u->forceFill(['phone' => '6281999'])->save();
    Carbon::setTestNow(Carbon::parse('2026-11-19 10:00', 'Asia/Jakarta'));
    $this->postJson('/api/whatsapp/fonnte/s', ['sender' => '6281999', 'message' => 'rekap', 'inboxid' => 'r1'])->assertOk();
    expect((string) Http::recorded()->last()[0]['message'])->toContain('Periode ini (25 Okt – 24 Nov 2026)');
});
