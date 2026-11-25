<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Cache::flush();
    Sanctum::actingAs(User::create(['name' => 'A', 'email' => 'g@x.id', 'password' => 'rahasia123', 'gender' => 'male']));
});

function xaus(float $perGram): array
{
    return ['xau' => ['price' => $perGram * 31.1034768, 'currency' => 'IDR', 'unit' => 'troy_oz'], 'price_as_of' => '2026-11-25T03:00:00Z', 'stale' => false];
}

it('memberi harga per gram dan menyimpannya 5 menit', function () {
    Http::fake(['xaus.com/*' => Http::response(xaus(2_000_000))]);
    $this->getJson('/api/gold-price')->assertOk()->assertJson(['price_per_gram' => 2_000_000, 'source' => 'xaus.com', 'stale' => false]);
    $this->getJson('/api/gold-price')->assertOk();
    Http::assertSentCount(1);
});

it('memakai sumber cadangan bila sumber utama gagal atau datanya tidak masuk akal', function (array|int $primary) {
    Http::fake([
        'xaus.com/*' => is_int($primary) ? Http::response('x', $primary) : Http::response($primary),
        'cdn.jsdelivr.net/*' => Http::response(['date' => '2026-11-23', 'xau' => ['idr' => 2_100_000 * 31.1034768]]),
    ]);
    $this->getJson('/api/gold-price')->assertJson(['price_per_gram' => 2_100_000, 'source' => 'currency-api (harian)']);
})->with([500, [['xau' => ['price' => 5, 'currency' => 'IDR']]]]);

it('memakai harga terakhir bila semua sumber mati, dan null bila belum pernah berhasil', function () {
    Http::fake(['*' => Http::response('x', 503)]);
    $this->getJson('/api/gold-price')->assertOk()->assertJson(['price_per_gram' => null, 'stale' => true]);

    Cache::flush();
    Cache::forever('gold:last', ['price_per_gram' => 1_900_000, 'as_of' => null, 'source' => 'xaus.com', 'stale' => false]);
    $this->getJson('/api/gold-price')->assertJson(['price_per_gram' => 1_900_000, 'stale' => true]);
});

it('harus login', function () {
    app('auth')->forgetGuards();
    $this->getJson('/api/gold-price')->assertUnauthorized();
});
