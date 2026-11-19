<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

function signup(string $email, string $gender = 'male'): User
{
    return User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => 'rahasia123', 'gender' => $gender]);
}

function pairUp(): array
{
    $andi = signup('andi@x.id', 'male');
    $rina = signup('rina@x.id', 'female');
    Sanctum::actingAs($andi);
    $code = test()->postJson('/api/household')->assertOk()->json('invite_code');
    Sanctum::actingAs($rina);
    test()->postJson('/api/household/join', ['code' => $code])->assertOk()->assertJsonCount(2, 'members');
    return [$andi, $rina];
}

function cat(string $name)
{
    return collect(test()->getJson('/api/categories')->json())->firstWhere('name', $name)['id'];
}
function myAcc()
{
    return test()->getJson('/api/accounts')->json()[0]['id'];
}
function tx(string $type, int $amount, string $category)
{
    return test()->postJson('/api/transactions', ['type' => $type, 'amount' => $amount, 'category_id' => cat($category), 'account_id' => myAcc()]);
}

it('menolak anggota ketiga dan kode sekali pakai', function () {
    Sanctum::actingAs(signup('a@x.id'));
    $code = $this->postJson('/api/household')->json('invite_code');
    Sanctum::actingAs(signup('b@x.id', 'female'));
    $this->postJson('/api/household/join', ['code' => $code])->assertOk();
    Sanctum::actingAs(signup('c@x.id'));
    $this->postJson('/api/household/join', ['code' => $code])->assertStatus(422);
});

it('memberi varian alt untuk pasangan sejenis kelamin', function () {
    Sanctum::actingAs(signup('a@x.id', 'female'));
    $code = $this->postJson('/api/household')->json('invite_code');
    Sanctum::actingAs(signup('b@x.id', 'female'));
    $res = $this->postJson('/api/household/join', ['code' => $code])->json('members');
    expect(collect($res)->firstWhere('role', 'partner')['color_variant'])->toBe('alt');
});

it('tiap anggota punya akun dan saldo sendiri', function () {
    [$andi, $rina] = pairUp();
    Sanctum::actingAs($andi);
    $this->getJson('/api/accounts')->assertJsonCount(1)->assertJsonPath('0.owner_id', $andi->id);
    tx('income', 10_000_000, 'Gaji')->assertCreated();
    tx('expense', 45_000, 'Makan & Minum')->assertCreated();
    $this->getJson('/api/summary')->assertJson(['balance' => 9_955_000, 'income' => 10_000_000, 'expense' => 45_000]);

    Sanctum::actingAs($rina);
    $this->getJson('/api/summary')->assertJson(['balance' => 0, 'income' => 0, 'expense' => 0]);
    expect($this->getJson('/api/transactions')->json())->toHaveCount(0);
    expect($this->getJson('/api/accounts')->json()[0]['owner_id'])->toBe($rina->id);
});

it('tidak bisa membaca, mengubah, atau memakai akun milik pasangan', function () {
    [$andi, $rina] = pairUp();
    Sanctum::actingAs($andi);
    $id = tx('income', 1000, 'Gaji')->json('id');
    $andiAcc = myAcc();

    Sanctum::actingAs($rina);
    $this->getJson("/api/transactions/$id")->assertNotFound();
    $this->deleteJson("/api/transactions/$id")->assertNotFound();
    $this->postJson('/api/transactions', ['type' => 'income', 'amount' => 5, 'category_id' => cat('Gaji'), 'account_id' => $andiAcc])->assertStatus(422);
});

it('transfer sudah tidak ada', function () {
    pairUp();
    $this->postJson('/api/transactions', ['type' => 'transfer', 'amount' => 5, 'account_id' => myAcc()])->assertStatus(422);
});

it('mengisolasi data antar dompet', function () {
    [$andi] = pairUp();
    Sanctum::actingAs($andi);
    $catId = cat('Gaji'); $acc = myAcc();
    Sanctum::actingAs(signup('orang3@x.id'));
    $this->postJson('/api/household')->assertOk();
    $this->postJson('/api/transactions', ['type' => 'income', 'amount' => 5, 'category_id' => $catId, 'account_id' => $acc])->assertStatus(422);
});

it('hapus lalu urungkan transaksi', function () {
    [$andi] = pairUp();
    Sanctum::actingAs($andi);
    $id = tx('income', 1000, 'Gaji')->json('id');
    $this->deleteJson("/api/transactions/$id")->assertNoContent();
    $this->getJson('/api/summary')->assertJson(['balance' => 0]);
    $this->postJson("/api/transactions/$id/restore")->assertOk();
    $this->getJson('/api/summary')->assertJson(['balance' => 1000]);
});

it('tabungan bersama: setoran mengurangi saldo pribadi si penyetor saja', function () {
    [$andi, $rina] = pairUp();
    Sanctum::actingAs($andi);
    tx('income', 10_000_000, 'Gaji');
    Sanctum::actingAs($rina);
    tx('income', 10_000_000, 'Gaji');

    Sanctum::actingAs($andi);
    $id = $this->postJson('/api/saving-goals', ['name' => 'Dana Darurat', 'target_amount' => 1000, 'icon' => 'Shield', 'color' => 'teal'])->json('id');
    $this->postJson("/api/saving-goals/$id/entries", ['amount' => 600])->assertCreated();
    Sanctum::actingAs($rina);
    $this->getJson('/api/saving-goals')->assertJsonCount(1); // tabungan terlihat oleh keduanya
    $this->postJson("/api/saving-goals/$id/entries", ['amount' => 400])->assertCreated();
    $this->postJson("/api/saving-goals/$id/entries", ['amount' => -5000])->assertStatus(422);
    $g = $this->getJson("/api/saving-goals/$id")->json();
    expect($g['saved'])->toBe(1000)->and($g['achieved_at'])->not->toBeNull();

    $this->getJson('/api/summary')->assertJson(['balance' => 10_000_000 - 400]);
    Sanctum::actingAs($andi);
    $this->getJson('/api/summary')->assertJson(['balance' => 10_000_000 - 600]);
});

it('anggaran pribadi menghitung terpakai milik sendiri', function () {
    [$andi, $rina] = pairUp();
    Sanctum::actingAs($andi);
    $c = cat('Makan & Minum');
    $this->putJson('/api/budgets', ['category_id' => $c, 'limit_amount' => 100000])->assertSuccessful();
    tx('expense', 30000, 'Makan & Minum');
    $this->getJson('/api/budgets')->assertJson(['total_limit' => 100000, 'total_spent' => 30000]);
    $this->getJson('/api/reports/monthly')->assertOk()->assertJsonPath('by_category.0.amount', 30000);

    Sanctum::actingAs($rina);
    tx('expense', 99000, 'Makan & Minum');
    $this->getJson('/api/budgets')->assertJson(['total_limit' => 0, 'total_spent' => 0]);
});
