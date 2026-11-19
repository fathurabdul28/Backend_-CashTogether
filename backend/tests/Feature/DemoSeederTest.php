<?php

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DemoSeeder;
use Laravel\Sanctum\Sanctum;

afterEach(fn () => Carbon::setTestNow());

it('mengisi data contoh: gaji 10 juta per orang tiap periode gajian dan Tabungan Nikah bersama', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-19 10:00', 'Asia/Jakarta'));
    $a = User::create(['name' => 'Fathur', 'email' => 'fathurtest@gmail.com', 'password' => 'rahasia123', 'gender' => 'male']);
    Sanctum::actingAs($a);
    $code = test()->postJson('/api/household')->json('invite_code');
    $w = User::create(['name' => 'Wulan', 'email' => 'wulantest@gmail.com', 'password' => 'rahasia123', 'gender' => 'female']);
    Sanctum::actingAs($w);
    test()->postJson('/api/household/join', ['code' => $code])->assertOk();

    $this->seed(DemoSeeder::class);

    expect($a->fresh()->cycle_start_day)->toBe(25)->and($w->fresh()->cycle_start_day)->toBe(25);
    foreach ([$a, $w] as $u) {
        Sanctum::actingAs($u->fresh());
        // periode berjalan (25 Okt – 24 Nov) dan periode sebelumnya (25 Sep – 24 Okt): masing-masing satu gaji 10 juta
        foreach (['2026-10', '2026-09'] as $key) {
            $s = $this->getJson("/api/summary?month=$key")->assertOk()->json();
            expect($s['income'])->toBe(10_000_000)->and($s['expense'])->toBeGreaterThan(0)->and($s['saved'])->toBeGreaterThan(0);
        }
        $this->getJson('/api/summary?month=2026-10')->assertJsonPath('label', '25 Okt – 24 Nov 2026');
        // saldo = 2 gaji - pengeluaran semua periode - 2 setoran
        $all = collect($this->getJson('/api/transactions?month=2026-09')->json())->merge($this->getJson('/api/transactions?month=2026-10')->json());
        $spent = $all->where('type', 'expense')->sum('amount');
        expect($all->where('type', 'income')->count())->toBe(2)
            ->and($all->max('occurred_at'))->toBeLessThanOrEqual(now()->utc()->toIso8601ZuluString('microsecond'));
        $this->getJson('/api/summary')->assertJsonPath('balance', 20_000_000 - $spent - 6_000_000);
        expect($this->getJson('/api/budgets')->json('items'))->not->toBeEmpty();
    }

    // tabungan bersama: satu target, setoran berdua
    Sanctum::actingAs($a->fresh());
    $goals = $this->getJson('/api/saving-goals')->assertOk()->json();
    expect($goals)->toHaveCount(1)->and($goals[0]['name'])->toBe('Tabungan Nikah')
        ->and($goals[0]['target_amount'])->toBe(100_000_000)->and($goals[0]['saved'])->toBe(12_000_000)
        ->and($goals[0]['monthly_needed'])->toBeGreaterThan(0);
    expect(array_values($goals[0]['contributions']))->toBe([6_000_000, 6_000_000]);

    // dijalankan lagi tidak menggandakan data
    $this->seed(DemoSeeder::class);
    expect(\App\Models\Transaction::where('type', 'income')->count())->toBe(4)
        ->and(\App\Models\SavingGoal::count())->toBe(1);
});
