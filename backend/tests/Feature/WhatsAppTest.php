<?php

use App\Models\Transaction;
use App\Models\User;
use App\Services\WhatsApp\MessageParser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

dataset('kalimat', [
    'contoh utama' => ['pengeluaran 34 ribu untuk makan', 'expense', 34_000, 'Makan & Minum'],
    'rb tanpa spasi' => ['beli bensin 50rb', 'expense', 50_000, 'Transportasi'],
    'k' => ['makan siang 25k', 'expense', 25_000, 'Makan & Minum'],
    'juta desimal koma' => ['bayar kontrakan 1,5 juta', 'expense', 1_500_000, 'Rumah'],
    'titik ribuan' => ['listrik 450.000', 'expense', 450_000, 'Tagihan & Listrik'],
    'rp' => ['jajan rp 15.000 kopi', 'expense', 15_000, 'Makan & Minum'],
    'angka polos' => ['pulsa 25000', 'expense', 25_000, 'Pulsa & Internet'],
    'gaji' => ['gaji 10 juta', 'income', 10_000_000, 'Gaji'],
    'pemasukan eksplisit' => ['pemasukan 500rb dari jualan', 'income', 500_000, 'Usaha'],
    'belanja harian menang atas belanja' => ['belanja harian 120rb', 'expense', 120_000, 'Belanja Harian'],
    'tanpa kategori' => ['pengeluaran 20rb', 'expense', 20_000, 'Lainnya'],
    'huruf besar' => ['PENGELUARAN 34 RIBU UNTUK MAKAN', 'expense', 34_000, 'Makan & Minum'],
]);

it('mem-parse kalimat sehari-hari', function (string $text, string $type, int $amount, string $category) {
    $p = (new MessageParser)->parse($text);
    expect($p)->not->toBeNull()
        ->and($p['type'])->toBe($type)->and($p['amount'])->toBe($amount)->and($p['category'])->toBe($category);
})->with('kalimat');

it('menolak kalimat tanpa nominal dan tidak salah baca "2 kopi" sebagai nominal', function () {
    expect((new MessageParser)->parse('halo apa kabar'))->toBeNull()
        ->and((new MessageParser)->parse('beli 2 kopi'))->toBeNull();
});

it('mengenali tanggal kemarin dan N hari lalu', function () {
    $now = Carbon::parse('2026-11-19 10:00', 'Asia/Jakarta');
    expect((new MessageParser)->parse('makan 20rb kemarin', $now)['date']->toDateString())->toBe('2026-11-18')
        ->and((new MessageParser)->parse('makan 20rb 3 hari lalu', $now)['date']->toDateString())->toBe('2026-11-16')
        ->and((new MessageParser)->parse('makan 20rb', $now)['date']->toDateString())->toBe('2026-11-19');
});

// ---- Alur webhook ----
beforeEach(function () {
    config(['services.fonnte.webhook_secret' => 'rahasia-test', 'services.fonnte.token' => 'tok', 'services.fonnte.bot_number' => '628111']);
    Http::fake(['api.fonnte.com/*' => Http::response(['status' => true], 200)]);
});

function wa(string $sender, string $message, array $extra = [])
{
    return test()->postJson('/api/whatsapp/fonnte/rahasia-test', ['sender' => $sender, 'message' => $message, 'inboxid' => uniqid()] + $extra);
}
function lastReply(): string
{
    $reqs = Http::recorded();
    return (string) $reqs->last()[0]['message'];
}
function linkedPair(): array
{
    $andi = User::create(['name' => 'Andi', 'email' => 'a@x.id', 'password' => 'rahasia123', 'gender' => 'male']);
    Sanctum::actingAs($andi);
    $code = test()->postJson('/api/household')->json('invite_code');
    $rina = User::create(['name' => 'Rina', 'email' => 'r@x.id', 'password' => 'rahasia123', 'gender' => 'female']);
    Sanctum::actingAs($rina);
    test()->postJson('/api/household/join', ['code' => $code]);
    return [$andi, $rina];
}

it('menolak webhook dengan rahasia salah', function () {
    $this->postJson('/api/whatsapp/fonnte/salah', ['sender' => '6281', 'message' => 'saldo'])->assertNotFound();
    Http::assertNothingSent();
});

it('menghubungkan nomor lewat kode lalu mencatat pengeluaran dan membalas', function () {
    [$andi] = linkedPair();
    Sanctum::actingAs($andi);
    $code = $this->postJson('/api/whatsapp/link-code')->assertOk()->json('code');
    expect($code)->toMatch('/^\d{6}$/');

    wa('0812-3456-7890', "HUBUNGKAN $code")->assertOk();
    expect(lastReply())->toContain('Terhubung');
    expect($andi->fresh()->phone)->toBe('6281234567890');

    wa('6281234567890', 'pengeluaran 34 ribu untuk makan')->assertOk();
    $tx = Transaction::where('created_by', $andi->id)->first();
    expect($tx->amount)->toBe(34_000)->and($tx->type)->toBe('expense')->and($tx->source)->toBe('whatsapp');
    expect(lastReply())->toContain('Rp34.000')->toContain('Makan & Minum')->toContain('hari ini')->toContain('Saldo kamu -Rp34.000');
});

it('nomor yang belum terhubung diberi petunjuk dan tidak mencatat apa pun', function () {
    wa('6289999', 'pengeluaran 10rb makan');
    expect(lastReply())->toContain('belum terhubung');
    expect(Transaction::count())->toBe(0);
});

it('kode tidak valid ditolak', function () {
    wa('6281', 'HUBUNGKAN 000000');
    expect(lastReply())->toContain('tidak valid');
});

it('transaksi masuk ke akun pemilik nomor, bukan pasangannya', function () {
    [$andi, $rina] = linkedPair();
    $andi->forceFill(['phone' => '6281111'])->save();
    $rina->forceFill(['phone' => '6282222'])->save();
    wa('6282222', 'gaji 10 juta');
    expect((int) Transaction::where('created_by', $rina->id)->sum('amount'))->toBe(10_000_000)
        ->and(Transaction::where('created_by', $andi->id)->count())->toBe(0);
    expect(lastReply())->toContain('Pemasukan')->toContain('Gaji');
});

it('perintah saldo, batal, dan pesan yang tidak dikenali', function () {
    [$andi] = linkedPair();
    $andi->forceFill(['phone' => '6281111'])->save();
    wa('6281111', 'gaji 5 juta');
    wa('6281111', 'beli bensin 50rb');
    wa('6281111', 'saldo');
    expect(lastReply())->toContain('Rp4.950.000');
    wa('6281111', 'batal');
    expect(lastReply())->toContain('Dibatalkan')->toContain('Rp50.000')->toContain('Rp5.000.000');
    expect(Transaction::count())->toBe(1);
    wa('6281111', 'halo');
    expect(lastReply())->toContain('belum bisa membaca');
});

it('memakai akun yang disebut di pesan', function () {
    [$andi] = linkedPair();
    $andi->forceFill(['phone' => '6281111'])->save();
    Sanctum::actingAs($andi);
    $bca = $this->postJson('/api/accounts', ['name' => 'BCA', 'type' => 'bank'])->json('id');
    wa('6281111', 'makan siang 25k pakai bca');
    expect(Transaction::first()->account_id)->toBe($bca);
    expect(lastReply())->toContain('Akun: BCA');
});

it('pesan duplikat dari Fonnte hanya diproses sekali', function () {
    [$andi] = linkedPair();
    $andi->forceFill(['phone' => '6281111'])->save();
    $payload = ['sender' => '6281111', 'message' => 'makan 10rb', 'inboxid' => 'sama-123'];
    $this->postJson('/api/whatsapp/fonnte/rahasia-test', $payload)->assertOk();
    $this->postJson('/api/whatsapp/fonnte/rahasia-test', $payload)->assertOk()->assertJson(['duplicate' => true]);
    expect(Transaction::count())->toBe(1);
});

it('pesan grup diabaikan', function () {
    [$andi] = linkedPair();
    $andi->forceFill(['phone' => '6281111'])->save();
    wa('6281111', 'makan 10rb', ['member' => '6283333']);
    expect(Transaction::count())->toBe(0);
});

it('GET ke URL webhook menjawab ok tanpa memproses apa pun, dan rahasia salah ditolak', function () {
    $this->getJson('/api/whatsapp/fonnte/rahasia-test')->assertOk()->assertJson(['ok' => true]);
    $this->getJson('/api/whatsapp/fonnte/salah')->assertNotFound();
    expect(Transaction::count())->toBe(0);
});

// ---- Beberapa barang dalam satu pesan ----
$F = 'Makan & Minum';
dataset('banyak', [
    'dua barang pakai "dan"' => ['saya makan ayam geprek 25 ribu dan minum kopi 80 ribu', [['expense', 25_000, $F], ['expense', 80_000, $F]]],
    'koma, tiga barang' => ['makan 50 ribu, kopi 80 ribu, bensin 20rb', [['expense', 50_000, $F], ['expense', 80_000, $F], ['expense', 20_000, 'Transportasi']]],
    'dua pemasukan' => ['gaji 10 juta dan bonus 2 juta', [['income', 10_000_000, 'Gaji'], ['income', 2_000_000, 'Bonus']]],
    'pemasukan + pengeluaran' => ['gaji 10 juta, beli bensin 50rb', [['income', 10_000_000, 'Gaji'], ['expense', 50_000, 'Transportasi']]],
    'kata jenis berlaku untuk semua' => ['pengeluaran: makan 25rb, kopi 15rb', [['expense', 25_000, $F], ['expense', 15_000, $F]]],
    'pemasukan tanpa kategori' => ['pemasukan 1 juta dan 500 ribu', [['income', 1_000_000, 'Lainnya'], ['income', 500_000, 'Lainnya']]],
    'tanpa pemisah' => ['makan 25rb kopi 80rb', [['expense', 25_000, $F], ['expense', 80_000, $F]]],
    'plus tanpa spasi' => ['makan 25rb+kopi 15rb', [['expense', 25_000, $F], ['expense', 15_000, $F]]],
    'ampersand' => ['kopi 15rb & roti 10rb', [['expense', 15_000, $F], ['expense', 10_000, $F]]],
    'koma desimal tidak memecah' => ['bayar kontrakan 1,5 juta dan listrik 450.000', [['expense', 1_500_000, 'Rumah'], ['expense', 450_000, 'Tagihan & Listrik']]],
    'titik ribuan' => ['makan 50.000, kopi 25.000', [['expense', 50_000, $F], ['expense', 25_000, $F]]],
    'angka polos' => ['bensin 50000 dan kopi 20000', [['expense', 50_000, 'Transportasi'], ['expense', 20_000, $F]]],
    'satu barang: "2 kopi" bukan nominal' => ['beli 2 kopi 25rb', [['expense', 25_000, $F]]],
    'satu barang: tahun bukan nominal' => ['makan 25rb 17 november 2026', [['expense', 25_000, $F]]],
    'satu barang biasa' => ['pengeluaran 34 ribu untuk makan', [['expense', 34_000, $F]]],
    'tanpa nominal' => ['halo apa kabar', []],
]);

it('membaca beberapa barang dalam satu pesan', function (string $text, array $expected) {
    $items = (new MessageParser)->parseMany($text, Carbon::parse('2026-11-19 10:00', 'Asia/Jakarta'));
    expect(array_map(fn ($i) => [$i['type'], $i['amount'], $i['category']], $items))->toBe($expected);
})->with('banyak');

it('catatan tiap barang bersih, dan tanggal berlaku untuk semua barang', function () {
    $now = Carbon::parse('2026-11-19 10:00', 'Asia/Jakarta');
    $i = (new MessageParser)->parseMany('saya makan ayam geprek 25 ribu dan minum kopi 80 ribu', $now);
    expect(array_column($i, 'note'))->toBe(['makan ayam geprek', 'minum kopi']);
    $y = (new MessageParser)->parseMany('kemarin makan 20rb dan kopi 15rb', $now);
    expect($y)->toHaveCount(2)->and($y[0]['date']->toDateString())->toBe('2026-11-18')->and($y[1]['date']->toDateString())->toBe('2026-11-18');
});

it('bot mencatat semua barang dari satu pesan, membalas daftar, dan BATAL membatalkan semuanya', function () {
    [$andi] = linkedPair();
    $andi->forceFill(['phone' => '6281111'])->save();

    wa('6281111', 'saya makan ayam geprek 25 ribu dan minum kopi 80 ribu')->assertOk();
    expect(Transaction::count())->toBe(2)->and((int) Transaction::sum('amount'))->toBe(105_000);
    expect(Transaction::pluck('created_at')->unique())->toHaveCount(1); // satu pesan = satu stempel waktu
    expect(lastReply())->toContain('Dicatat 2 transaksi')->toContain('1. Pengeluaran Rp25.000 · Makan & Minum (makan ayam geprek)')
        ->toContain('2. Pengeluaran Rp80.000 · Makan & Minum (minum kopi)')->toContain('Saldo kamu -Rp105.000')->toContain('BATAL');

    wa('6281111', 'batal')->assertOk();
    expect(Transaction::count())->toBe(0);
    expect(lastReply())->toContain('Dibatalkan 2 transaksi')->toContain('Saldo kamu Rp0');
});

it('BATAL hanya membatalkan pesan terakhir, pesan sebelumnya tetap', function () {
    [$andi] = linkedPair();
    $andi->forceFill(['phone' => '6281111'])->save();
    wa('6281111', 'gaji 5 juta');
    Carbon::setTestNow(now()->addSeconds(5));
    wa('6281111', 'bensin 50rb dan kopi 20rb');
    expect(Transaction::count())->toBe(3);
    wa('6281111', 'batal');
    expect(Transaction::count())->toBe(1)->and(Transaction::first()->type)->toBe('income');
    wa('6281111', 'saldo');
    expect(lastReply())->toContain('Rp5.000.000');
    Carbon::setTestNow();
});

it('pemasukan dan pengeluaran bisa dalam satu pesan', function () {
    [$andi] = linkedPair();
    $andi->forceFill(['phone' => '6281111'])->save();
    wa('6281111', 'gaji 10 juta dan beli bensin 50rb');
    expect(lastReply())->toContain('Pemasukan Rp10.000.000 · Gaji')->toContain('Pengeluaran Rp50.000 · Transportasi')->toContain('Saldo kamu Rp9.950.000');
});

it('menolak pesan dengan lebih dari 10 barang dan tidak mencatat apa pun', function () {
    [$andi] = linkedPair();
    $andi->forceFill(['phone' => '6281111'])->save();
    wa('6281111', implode(', ', array_fill(0, 11, 'kopi 15rb')));
    expect(lastReply())->toContain('Maksimal 10 transaksi');
    expect(Transaction::count())->toBe(0);
});
