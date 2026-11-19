<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

afterEach(fn () => Carbon::setTestNow());

function salaryUser(string $email = 'g@x.id'): User
{
    $u = User::create(['name' => 'Fathur', 'email' => $email, 'password' => 'rahasia123', 'gender' => 'male']);
    Sanctum::actingAs($u);
    test()->postJson('/api/household')->assertOk();
    return $u;
}

function salaryPayload(User $u, array $extra = []): array
{
    return $extra + [
        'type' => 'income', 'amount' => 6000000, 'note' => 'Gaji', 'occurred_at' => '2026-09-25T01:00:00Z',
        'category_id' => Category::where('household_id', $u->householdId())->where('kind', 'income')->value('id'),
        'account_id' => Account::where('owner_id', $u->id)->value('id'),
    ];
}

it('gaji dengan repeat_monthly dicatat otomatis tiap bulan sampai bulan ini', function () {
    Carbon::setTestNow('2026-09-25 10:00:00');
    $u = salaryUser();
    $this->postJson('/api/transactions', salaryPayload($u, ['repeat_monthly' => true]))->assertCreated();
    expect(Transaction::where('type', 'income')->count())->toBe(1); // bulan ini sudah dicatat manual

    Carbon::setTestNow('2026-12-26 10:00:00'); // 3 bulan kemudian
    $this->getJson('/api/transactions?month=2026-12')->assertOk();
    $tx = Transaction::where('type', 'income')->orderBy('occurred_at')->get();
    expect($tx)->toHaveCount(4)->and($tx->pluck('amount')->unique()->all())->toBe([6000000])
        ->and($tx->skip(1)->pluck('source')->unique()->all())->toBe(['recurring']);

    $this->getJson('/api/summary')->assertOk(); // dipanggil lagi: tidak ada duplikat
    expect(Transaction::where('type', 'income')->count())->toBe(4);
});

it('tidak membuat gaji sebelum tanggalnya tiba', function () {
    Carbon::setTestNow('2026-09-25 10:00:00');
    $u = salaryUser();
    $this->postJson('/api/transactions', salaryPayload($u, ['repeat_monthly' => true]))->assertCreated();
    Carbon::setTestNow('2026-10-20 10:00:00');
    $this->getJson('/api/summary')->assertOk();
    expect(Transaction::where('type', 'income')->count())->toBe(1);
    Carbon::setTestNow('2026-10-25 09:00:00');
    $this->getJson('/api/summary')->assertOk();
    expect(Transaction::where('type', 'income')->count())->toBe(2);
});

it('mengubah nominal hanya berlaku untuk bulan berikutnya, berhenti tidak menghapus riwayat', function () {
    Carbon::setTestNow('2026-09-25 10:00:00');
    $u = salaryUser();
    $this->postJson('/api/transactions', salaryPayload($u, ['repeat_monthly' => true]))->assertCreated();
    $id = $this->getJson('/api/recurring')->assertOk()->json('0.id');

    Carbon::setTestNow('2026-10-26 10:00:00');
    $this->getJson('/api/summary');
    $this->patchJson("/api/recurring/{$id}", ['amount' => 7000000])->assertOk();
    Carbon::setTestNow('2026-11-26 10:00:00');
    $this->getJson('/api/summary');
    expect(Transaction::where('type', 'income')->orderBy('occurred_at')->pluck('amount')->all())->toBe([6000000, 6000000, 7000000]);

    $this->patchJson("/api/recurring/{$id}", ['active' => false])->assertOk();
    Carbon::setTestNow('2027-03-26 10:00:00');
    $this->getJson('/api/summary');
    expect(Transaction::where('type', 'income')->count())->toBe(3);
    $this->deleteJson("/api/recurring/{$id}")->assertNoContent();
    expect(Transaction::where('type', 'income')->count())->toBe(3);
});

it('aturan rutin bersifat pribadi dan pengeluaran tidak bisa berulang', function () {
    Carbon::setTestNow('2026-09-25 10:00:00');
    $a = salaryUser('a@x.id');
    $this->postJson('/api/transactions', salaryPayload($a, ['repeat_monthly' => true]))->assertCreated();
    $id = $this->getJson('/api/recurring')->json('0.id');

    $b = salaryUser('b@x.id');
    $this->getJson('/api/recurring')->assertOk()->assertJsonCount(0);
    $this->patchJson("/api/recurring/{$id}", ['amount' => 1])->assertNotFound();

    $exp = ['type' => 'expense', 'amount' => 5000, 'repeat_monthly' => true,
        'category_id' => Category::where('household_id', $b->householdId())->where('kind', 'expense')->value('id'),
        'account_id' => Account::where('owner_id', $b->id)->value('id')];
    $this->postJson('/api/transactions', $exp)->assertCreated();
    $this->getJson('/api/recurring')->assertJsonCount(0);
});

it('POST /recurring langsung membuat aturan dan memvalidasi tanggal', function () {
    Carbon::setTestNow('2026-09-25 10:00:00');
    $u = salaryUser();
    $p = ['amount' => 6000000, 'category_id' => Category::where('household_id', $u->householdId())->where('kind', 'income')->value('id'),
        'account_id' => Account::where('owner_id', $u->id)->value('id'), 'day' => 25, 'start_month' => '2026-09'];
    $this->postJson('/api/recurring', array_merge($p, ['day' => 31]))->assertUnprocessable();
    $this->postJson('/api/recurring', $p)->assertCreated();
    expect(Transaction::where('type', 'income')->count())->toBe(1);
});

it('menyalakan kembali gaji rutin tidak mencatat bulan yang terlewat', function () {
    Carbon::setTestNow('2026-09-25 10:00:00');
    $u = salaryUser();
    $this->postJson('/api/transactions', salaryPayload($u, ['repeat_monthly' => true]))->assertCreated();
    $id = $this->getJson('/api/recurring')->json('0.id');
    $this->patchJson("/api/recurring/{$id}", ['active' => false])->assertOk();
    Carbon::setTestNow('2027-02-10 10:00:00');
    $this->patchJson("/api/recurring/{$id}", ['active' => true])->assertOk();
    expect(Transaction::where('type', 'income')->count())->toBe(1);
    Carbon::setTestNow('2027-02-25 10:00:00');
    $this->getJson('/api/summary');
    expect(Transaction::where('type', 'income')->count())->toBe(2);
});
