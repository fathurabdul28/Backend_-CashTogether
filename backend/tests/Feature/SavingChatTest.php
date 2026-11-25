<?php

use App\Models\SavingEntry;
use App\Models\SavingGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\WhatsApp\MessageParser;
use App\Support\Wallet;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config(['services.fonnte.webhook_secret' => 'rahasia-test', 'services.fonnte.token' => 'tok', 'services.fonnte.bot_number' => '628111']);
    Http::fake(['api.fonnte.com/*' => Http::response(['status' => true], 200)]);
});

function chat(string $message): string
{
    test()->postJson('/api/whatsapp/fonnte/rahasia-test', ['sender' => '6281234', 'message' => $message, 'inboxid' => uniqid()])->assertOk();
    return (string) Http::recorded()->last()[0]['message'];
}

function saver(array $goals = ['Tabungan Nikah']): User
{
    $u = User::create(['name' => 'Fathur', 'email' => 'n@x.id', 'password' => 'rahasia123', 'gender' => 'male']);
    Sanctum::actingAs($u);
    test()->postJson('/api/household')->assertOk();
    $u->forceFill(['phone' => '6281234'])->save();
    foreach ($goals as $name) {
        test()->postJson('/api/saving-goals', ['name' => $name, 'target_amount' => 10_000_000, 'icon' => 'Heart', 'color' => 'sage'])->assertCreated();
    }
    return $u;
}

function balanceOf(User $u): int
{
    return Wallet::balance($u->id, $u->householdId());
}

it('mengenali kalimat menabung, bukan tabung gas atau penarikan', function (string $text, ?string $saving) {
    expect((new MessageParser)->parse($text)['saving'])->toBe($saving);
})->with([
    ['nabung 1 juta', 'deposit'], ['menabung 500rb', 'deposit'], ['setor tabungan 200rb', 'deposit'],
    ['tabungan nikah 1 juta', 'deposit'], ['masuk tabungan 1jt', 'deposit'], ['nabung dari gaji 1 juta', 'deposit'],
    ['beli tabung gas 25rb', null], ['bunga tabungan 5000', null], ['ambil uang tabungan 500rb', 'withdraw'],
]);

it('"nabung 1 juta" masuk pengeluaran dan tabungan, saldo hanya terpotong sekali', function () {
    $u = saver();
    chat('gaji 6 juta');
    $reply = chat('nabung 1 juta');

    expect($reply)->toContain('Tabungan Nikah')->toContain('Rp1.000.000')->toContain('(10%)')->toContain('Saldo kamu Rp5.000.000');
    expect((int) Transaction::where('type', 'expense')->sum('amount'))->toBe(1_000_000)
        ->and((int) SavingEntry::sum('amount'))->toBe(1_000_000)
        ->and(balanceOf($u))->toBe(5_000_000);
    $this->getJson('/api/summary')->assertJson(['balance' => 5_000_000, 'expense' => 1_000_000, 'saved' => 1_000_000]);
});

it('BATAL, ubah nominal, hapus, dan pulihkan menjaga tabungan tetap sinkron', function () {
    $u = saver();
    chat('nabung 1 juta');
    chat('batal');
    expect(SavingEntry::count())->toBe(0)->and(balanceOf($u))->toBe(0);

    chat('nabung 1 juta');
    $id = Transaction::value('id');
    $this->patchJson("/api/transactions/{$id}", ['amount' => 700_000])->assertOk();
    expect((int) SavingEntry::sum('amount'))->toBe(700_000)->and(balanceOf($u))->toBe(-700_000);
    $this->deleteJson("/api/transactions/{$id}")->assertNoContent();
    expect(SavingEntry::count())->toBe(0)->and(balanceOf($u))->toBe(0);
    $this->postJson("/api/transactions/{$id}/restore")->assertOk();
    expect((int) SavingEntry::sum('amount'))->toBe(700_000)->and(balanceOf($u))->toBe(-700_000);
});

it('menghapus tabungan mengembalikan uangnya ke saldo', function () {
    $u = saver();
    chat('nabung 1 juta');
    $this->deleteJson('/api/saving-goals/'.SavingGoal::value('id'))->assertNoContent();
    expect(Transaction::count())->toBe(0)->and(balanceOf($u))->toBe(0);
});

it('memilih tabungan dari namanya, bertanya bila tidak jelas', function () {
    saver(['Tabungan Nikah', 'Liburan']);
    expect(chat('nabung 1 juta'))->toContain('yang mana')->toContain('Liburan');
    expect(Transaction::count())->toBe(0);
    expect(chat('nabung 1 juta buat liburan'))->toContain('ke Liburan');
    expect(SavingEntry::value('goal_id'))->toBe(SavingGoal::where('name', 'Liburan')->value('id'));
});

it('tanpa tabungan atau untuk penarikan tidak mencatat apa pun', function () {
    saver([]);
    expect(chat('nabung 1 juta'))->toContain('Belum ada tabungan');
    expect(chat('ambil tabungan 500rb'))->toContain('lewat app');
    expect(Transaction::count())->toBe(0);
});

it('bisa digabung dengan barang lain dalam satu pesan', function () {
    $u = saver();
    $reply = chat('makan 25rb dan nabung 1 juta');
    expect($reply)->toContain('Dicatat 2 transaksi')->toContain('→ Tabungan Nikah')->toContain('terkumpul Rp1.000.000');
    expect((int) SavingEntry::sum('amount'))->toBe(1_000_000)->and(balanceOf($u))->toBe(-1_025_000);
});
