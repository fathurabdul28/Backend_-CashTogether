<?php

use App\Models\SavingEntry;
use App\Models\SavingGoal;

it('mengubah rupiah tabungan menjadi emas tanpa menyentuh saldo, lalu bisa dibatalkan', function () {
    $u = saver(['Rumah']);
    $id = SavingGoal::value('id');
    $this->postJson("/api/saving-goals/{$id}/entries", ['amount' => 5_000_000])->assertCreated();
    expect(balanceOf($u))->toBe(-5_000_000);

    $entry = $this->postJson("/api/saving-goals/{$id}/convert", ['amount' => 2_000_000, 'gold_grams' => 1])->assertCreated()->json('id');
    $this->postJson("/api/saving-goals/{$id}/convert", ['amount' => 1_000_000, 'gold_grams' => 0.5])->assertCreated();
    $g = $this->getJson("/api/saving-goals/{$id}")->json();
    expect($g['saved'])->toBe(2_000_000)->and((float) $g['gold_grams'])->toBe(1.5)
        ->and($g['contributions'][$u->id])->toBe(5_000_000)->and(balanceOf($u))->toBe(-5_000_000);

    $this->deleteJson("/api/saving-goals/{$id}/conversions/{$entry}")->assertNoContent();
    $g = $this->getJson("/api/saving-goals/{$id}")->json();
    expect($g['saved'])->toBe(4_000_000)->and((float) $g['gold_grams'])->toBe(0.5);
});

it('menolak konversi melebihi isi tabungan, gram nol, dan membatalkan setoran biasa', function () {
    saver(['Rumah']);
    $id = SavingGoal::value('id');
    $dep = $this->postJson("/api/saving-goals/{$id}/entries", ['amount' => 1_000_000])->json('id');
    $this->postJson("/api/saving-goals/{$id}/convert", ['amount' => 2_000_000, 'gold_grams' => 1])->assertUnprocessable();
    $this->postJson("/api/saving-goals/{$id}/convert", ['amount' => 500_000, 'gold_grams' => 0])->assertUnprocessable();
    $this->deleteJson("/api/saving-goals/{$id}/conversions/{$dep}")->assertUnprocessable();
    expect(SavingEntry::count())->toBe(1);
});
