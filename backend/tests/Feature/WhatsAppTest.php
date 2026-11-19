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
