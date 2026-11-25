<?php

use App\Models\SavingEntry;
use App\Models\SavingGoal;
use App\Models\Transaction;
use App\Services\WhatsApp\SavingListParser;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.fonnte.webhook_secret' => 'rahasia-test', 'services.fonnte.token' => 'tok', 'services.fonnte.bot_number' => '628111']);
    Http::fake(['api.fonnte.com/*' => Http::response(['status' => true], 200)]);
});

const DAFTAR = <<<'TXT'
tabungan dulu untuk seserahan dan nabung
 Seserahan:
 1. Jam + dompet : Rp 153.000
 2. Tas mosdom : Rp 200.000
 3. Handuk : Rp 200.000
 4. Al-Quran : Rp 200.000
 5. Sepatu : Rp 130.000
 6. Sajadah : Rp 200.000
 7. Fashion (jam, kalung, gelang, cincin,)
 8. cincin emas : 1,5 Gram 
 9. Mukena : Rp. 170.000
10. Piyama : Rp. 180.000

Tabungan Rumah
25 Oktober 2026 : Rp. 2.463.000 + 2 Gram Logam Mulia
TXT;

it('membaca daftar seserahan dan tabungan rumah apa adanya', function () {
    [$s, $r] = (new SavingListParser)->parse(DAFTAR);
    expect($s['name'])->toBe('Seserahan')->and($s['items'])->toHaveCount(10)
        ->and(array_sum(array_column($s['items'], 'amount')))->toBe(1_433_000)
        ->and($s['items'][6]['name'])->toBe('Fashion (jam, kalung, gelang, cincin,)')->and($s['items'][6]['amount'])->toBe(0)
        ->and($s['items'][7]['grams'])->toBe(1.5)
        ->and($r['name'])->toBe('Tabungan Rumah')->and($r['items'][0]['amount'])->toBe(2_463_000)
        ->and($r['items'][0]['grams'])->toBe(2.0)->and($r['items'][0]['date']->format('Y-m-d'))->toBe('2026-10-25');
});

it('pesan transaksi biasa banyak baris bukan daftar tabungan', function (string $text) {
    expect((new SavingListParser)->parse($text, ['Tabungan Nikah']))->toBe([]);
})->with(["makan 25rb\nkopi 15rb", "pengeluaran hari ini:\nmakan 20rb", 'nabung 1 juta', "gaji 5 juta\nnabung 1 juta"]);

it('mencatat daftar tanpa memotong saldo, kirim ulang tidak dobel, BATAL membersihkan', function () {
    $u = saver([]);
    $reply = chat(DAFTAR);
    expect($reply)->toContain('Seserahan (baru)')->toContain('Rp1.433.000 + 1,5 gram emas')->toContain('Rp2.463.000 + 2 gram emas');
    expect(Transaction::count())->toBe(0)->and(balanceOf($u))->toBe(0);

    $goals = collect($this->getJson('/api/saving-goals')->assertOk()->json())->keyBy('name');
    expect($goals['Seserahan']['saved'])->toBe(1_433_000)->and($goals['Seserahan']['target_amount'])->toBe(0)
        ->and($goals['Seserahan']['achieved_at'])->toBeNull()->and((float) $goals['Tabungan Rumah']['gold_grams'])->toBe(2.0);
    $this->getJson('/api/saving-goals/'.$goals['Seserahan']['id'])->assertJsonCount(10, 'entries');

    expect(chat(DAFTAR))->toContain('sudah ada');
    expect(SavingEntry::count())->toBe(11);

    expect(chat('batal'))->toContain('11 rincian');
    expect(SavingEntry::count())->toBe(0)->and(SavingGoal::count())->toBe(0);
});

it('menambah ke tabungan yang sudah ada; nabung biasa tetap memotong saldo', function () {
    $u = saver(['Rumah']);
    chat('nabung 1 juta');
    chat("Tabungan Rumah\n25 Oktober 2026 : Rp 2.463.000");
    expect(SavingGoal::count())->toBe(1)->and((int) SavingEntry::sum('amount'))->toBe(3_463_000)->and(balanceOf($u))->toBe(-1_000_000);

    chat('batal'); // daftar yang terakhir
    expect((int) SavingEntry::sum('amount'))->toBe(1_000_000)->and(Transaction::count())->toBe(1);
    chat('batal'); // lalu nabung
    expect(SavingEntry::count())->toBe(0)->and(balanceOf($u))->toBe(0)->and(SavingGoal::count())->toBe(1);
});
