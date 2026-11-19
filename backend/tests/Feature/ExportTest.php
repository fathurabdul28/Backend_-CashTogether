<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
use OpenSpout\Reader\XLSX\Reader;
use Laravel\Sanctum\Sanctum;

afterEach(fn () => Carbon::setTestNow());

/** Dua akun satu dompet; Andi siklus 25, masing-masing punya transaksi sendiri. */
function exportPair(): array
{
    $a = User::create(['name' => 'Andi', 'email' => 'a@x.id', 'password' => 'rahasia123', 'gender' => 'male', 'cycle_start_day' => 25]);
    Sanctum::actingAs($a);
    $code = test()->postJson('/api/household')->json('invite_code');
    $r = User::create(['name' => 'Rina', 'email' => 'r@x.id', 'password' => 'rahasia123', 'gender' => 'female']);
    Sanctum::actingAs($r);
    test()->postJson('/api/household/join', ['code' => $code])->assertOk();
    return [$a->fresh(), $r->fresh()];
}

function exTx(User $u, int $amount, string $at, string $type = 'expense', string $note = ''): void
{
    $hid = $u->householdId();
    Transaction::create([
        'household_id' => $hid, 'type' => $type, 'amount' => $amount, 'note' => $note, 'occurred_at' => Carbon::parse($at)->utc(),
        'category_id' => Category::where('household_id', $hid)->where('kind', $type)->value('id'),
        'account_id' => Account::where('owner_id', $u->id)->value('id'), 'created_by' => $u->id, 'paid_by' => $u->id,
    ]);
}

/** Baca semua sheet XLSX menjadi [namaSheet => [baris => [sel...]]]. */
function readXlsx(string $path): array
{
    $reader = new Reader();
    $reader->open($path);
    $out = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $out[$sheet->getName()][] = array_map(fn ($v) => $v instanceof DateTimeInterface ? $v->format('Y-m-d') : $v, $row->toArray());
        }
    }
    $reader->close();
    return $out;
}

it('meminta tautan butuh login, dan tautan berlaku singkat', function () {
    $this->postJson('/api/export/link')->assertUnauthorized();
    [$a] = exportPair();
    Sanctum::actingAs($a);
    $res = $this->postJson('/api/export/link?month=2026-10')->assertOk();
    expect($res->json('url'))->toContain('/api/export/download')->toContain('signature=')
        ->and($res->json('filename'))->toBe('CashTogether_2026-10-25_sd_2026-11-24.xlsx');
});

it('mengunduh .xlsx berisi transaksi periode milik sendiri saja', function () {
    [$a, $r] = exportPair();
    exTx($a, 10_000_000, '2026-10-25T09:00:00+07:00', 'income', 'Gaji Oktober');
    exTx($a, 45_000, '2026-10-26T12:10:00+07:00', 'expense', 'Makan siang');
    exTx($a, 99_000, '2026-10-24T23:30:00+07:00', 'expense', 'Periode sebelumnya');   // di luar periode
    exTx($r, 777_000, '2026-10-26T12:00:00+07:00', 'expense', 'Punya pasangan');        // milik orang lain
    Sanctum::actingAs($a);
    $url = $this->postJson('/api/export/link?month=2026-10')->json('url');

    // Unduhan tidak butuh login: keabsahan dari tanda tangan
    $this->app['auth']->forgetGuards();
    $res = $this->get($url)->assertOk();
    expect($res->headers->get('content-type'))->toContain('spreadsheetml')
        ->and($res->headers->get('content-disposition'))->toContain('CashTogether_2026-10-25_sd_2026-11-24.xlsx');

    $book = readXlsx($res->baseResponse->getFile()->getPathname());
    expect(array_keys($book))->toBe(['Transaksi', 'Ringkasan', 'Tabungan']);

    $rows = $book['Transaksi'];
    expect($rows[0])->toBe(['Tanggal', 'Jam', 'Jenis', 'Kategori', 'Akun', 'Nominal (Rp)', 'Catatan', 'Sumber'])
        ->and($rows)->toHaveCount(3); // header + 2 transaksi periode ini
    expect($rows[1][0])->toBe('2026-10-25')->and($rows[1][2])->toBe('Pemasukan')->and($rows[1][5])->toBe(10_000_000)->and($rows[1][6])->toBe('Gaji Oktober');
    expect($rows[2][1])->toBe('12:10')->and($rows[2][2])->toBe('Pengeluaran')->and($rows[2][5])->toBe(45_000);
    $flat = json_encode($book);
    expect($flat)->not->toContain('Punya pasangan')->not->toContain('Periode sebelumnya');

    $sum = collect($book['Ringkasan'])->keyBy(0);
    expect($sum['Periode'][1])->toBe('25 Okt – 24 Nov 2026')
        ->and($sum['Total pemasukan'][1])->toBe(10_000_000)->and($sum['Total pengeluaran'][1])->toBe(45_000)
        ->and($sum['Selisih'][1])->toBe(9_955_000);
});

it('scope=all memuat semua periode, dan sheet Tabungan memuat setoran kedua orang', function () {
    [$a, $r] = exportPair();
    exTx($a, 1_000, '2026-08-05T10:00:00+07:00');
    exTx($a, 2_000, '2026-10-30T10:00:00+07:00');
    Sanctum::actingAs($a);
    $gid = $this->postJson('/api/saving-goals', ['name' => 'Tabungan Nikah', 'target_amount' => 100_000_000, 'icon' => 'Heart', 'color' => 'plum'])->json('id');
    $this->postJson("/api/saving-goals/$gid/entries", ['amount' => 3_000_000])->assertCreated();
    Sanctum::actingAs($r);
    $this->postJson("/api/saving-goals/$gid/entries", ['amount' => 2_000_000])->assertCreated();

    Sanctum::actingAs($a);
    $url = $this->postJson('/api/export/link?scope=all')->json('url');
    $this->app['auth']->forgetGuards();
    $res = $this->get($url)->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('CashTogether_semua-data.xlsx');
    $book = readXlsx($res->baseResponse->getFile()->getPathname());
    expect($book['Transaksi'])->toHaveCount(3);
    $goal = $book['Tabungan'][1];
    expect($goal[0])->toBe('Tabungan Nikah')->and($goal[2])->toBe(100_000_000)->and($goal[3])->toBe(5_000_000)
        ->and($goal[5])->toBe(3_000_000)->and($goal[6])->toBe(2_000_000);
    expect($book['Tabungan'][0][6])->toBe('Setoran Rina (Rp)');
});

it('menolak tautan yang diubah atau sudah kedaluwarsa', function () {
    [$a, $r] = exportPair();
    Sanctum::actingAs($a);
    $url = $this->postJson('/api/export/link?month=2026-10')->json('url');
    $this->app['auth']->forgetGuards();

    // ganti pemilik (u=...) -> tanda tangan tidak cocok, tidak bisa mengambil data orang lain
    $this->get(preg_replace('/u='.$a->id.'/', 'u='.$r->id, $url))->assertForbidden();
    $this->get(str_replace('month=2026-10', 'month=2026-09', $url))->assertForbidden();
    $this->get('/api/export/download?u='.$a->id.'&month=2026-10')->assertForbidden(); // tanpa signature

    Carbon::setTestNow(now()->addMinutes(6));
    $this->get($url)->assertForbidden();
});
