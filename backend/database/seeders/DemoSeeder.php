<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\HouseholdMember;
use App\Models\SavingEntry;
use App\Models\SavingGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Period;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk dua akun satu dompet: gaji Rp10.000.000 per orang tiap tanggal gajian,
 * pengeluaran sehari-hari, dan satu tabungan bersama "Tabungan Nikah".
 *
 *   php artisan db:seed --class=DemoSeeder
 *   DEMO_EMAIL=lain@x.id DEMO_CYCLE=25 DEMO_FRESH=1 php artisan db:seed --class=DemoSeeder
 *
 * DEMO_EMAIL  salah satu email dari pasangan (default fathurtest@gmail.com)
 * DEMO_CYCLE  tanggal gajian = awal periode (default 25)
 * DEMO_FRESH  hapus transaksi/anggaran/tabungan/akun dompet itu dulu, lalu isi ulang.
 * DEMO_APPEND tambahkan data contoh di samping data yang sudah ada, tanpa menghapus apa pun (jangan diulang: akan menggandakan).
 * Tanpa keduanya, seeder menolak jalan bila dompet sudah punya transaksi.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $him = User::where('email', env('DEMO_EMAIL', 'fathurtest@gmail.com'))->firstOrFail();
        $hid = HouseholdMember::where('user_id', $him->id)->value('household_id');
        abort_if(! $hid, 1, 'User belum punya dompet.');
        $ids = HouseholdMember::where('household_id', $hid)->pluck('user_id');
        abort_if($ids->count() < 2, 1, 'Dompet belum berisi 2 orang.');
        $her = $ids->first(fn ($id) => $id !== $him->id);
        $cycle = Period::clampDay((int) env('DEMO_CYCLE', 25));

        if (env('DEMO_FRESH')) {
            Transaction::withTrashed()->where('household_id', $hid)->forceDelete();
            Budget::where('household_id', $hid)->delete();
            SavingGoal::where('household_id', $hid)->delete(); // setoran ikut terhapus (cascade)
            Account::where('household_id', $hid)->where(fn ($q) => $q->whereNull('owner_id')->orWhereIn('owner_id', $ids))->where('name', '!=', 'Tunai')->delete();
        } elseif (! env('DEMO_APPEND') && Transaction::where('household_id', $hid)->exists()) {
            $this->command->warn('Dompet sudah punya transaksi, seeder dilewati. DEMO_FRESH=1 mengganti semuanya; DEMO_APPEND=1 menambah tanpa menghapus.');
            return;
        }

        User::whereIn('id', $ids)->update(['cycle_start_day' => $cycle]);

        $now = Carbon::now(Period::tz());
        $cur = Period::currentKey($cycle, $now);
        $periods = [Period::previousKey($cur), $cur]; // satu periode penuh + periode berjalan

        $cat = fn (string $n, string $k) => Category::where('household_id', $hid)->where('name', $n)->where('kind', $k)->value('id');
        $account = fn (int $uid, string $name, string $type) => Account::firstOrCreate(
            ['household_id' => $hid, 'owner_id' => $uid, 'name' => $name], ['type' => $type, 'initial_balance' => 0])->id;

        // [hari ke-N sejak awal periode, kategori, nominal, akun('t' tunai | 'b' BCA), catatan]
        $plans = [
            $him->id => [
                [2, 'Rumah', 1_800_000, 'b', 'Cicilan kontrakan'], [3, 'Pulsa & Internet', 350_000, 'b', 'Wifi & pulsa'],
                [5, 'Makan & Minum', 58_000, 't', 'Makan siang'], [6, 'Transportasi', 175_000, 't', 'Bensin'],
                [9, 'Hiburan', 240_000, 'b', 'Nonton & makan malam'], [10, 'Makan & Minum', 135_000, 't', 'Makan keluarga'],
                [14, 'Makan & Minum', 72_000, 't', 'Sarapan & kopi'], [16, 'Hadiah & Sosial', 200_000, 't', 'Kado ulang tahun'],
                [17, 'Transportasi', 155_000, 't', 'Bensin'], [22, 'Makan & Minum', 120_000, 't', 'Makan malam'],
                [25, 'Cicilan', 500_000, 'b', 'Cicilan HP'],
            ],
            $her => [
                [3, 'Tagihan & Listrik', 650_000, 'b', 'Listrik & air'], [4, 'Belanja Harian', 420_000, 't', 'Belanja mingguan'],
                [7, 'Makan & Minum', 96_000, 't', 'Kopi & roti'], [8, 'Belanja Harian', 380_000, 't', 'Belanja mingguan'],
                [11, 'Kesehatan', 300_000, 'b', 'Vitamin & obat'], [12, 'Transportasi', 165_000, 't', 'Ojek & parkir'],
                [13, 'Belanja Harian', 410_000, 't', 'Belanja mingguan'], [15, 'Belanja', 450_000, 'b', 'Baju kerja'],
                [18, 'Makan & Minum', 88_000, 't', 'Makan siang'], [20, 'Belanja Harian', 395_000, 't', 'Belanja mingguan'],
            ],
        ];

        $goal = SavingGoal::firstOrCreate(
            ['household_id' => $hid, 'name' => 'Tabungan Nikah'],
            ['target_amount' => 100_000_000, 'icon' => 'Heart', 'color' => 'plum', 'target_date' => $now->copy()->addMonths(24)->toDateString(), 'created_by' => $him->id],
        );

        foreach ([$him->id => 'Gaji Fathur', $her => 'Gaji Wulan'] as $uid => $salaryNote) {
            $acc = ['t' => $account($uid, 'Tunai', 'cash'), 'b' => $account($uid, 'BCA', 'bank')];
            foreach ($periods as $key) {
                [$start] = Period::local($key, $cycle);
                $at = fn (int $dayOffset, ?int $hour = null) => $start->copy()->addDays($dayOffset)->setTime($hour ?? rand(8, 20), rand(0, 59));
                // gaji tepat di tanggal mulai periode (offset 0); pengeluaran hari ke-N berarti N-1 hari setelah awal periode
                $rows = array_merge(
                    [[0, 'Gaji', 10_000_000, 'b', $salaryNote]],
                    array_map(fn ($r) => [$r[0] - 1, $r[1], $r[2], $r[3], $r[4]], $plans[$uid]),
                );
                foreach ($rows as $r) {
                    [$offset, $c, $amt, $a, $note] = [$r[0], $r[1], $r[2], $r[3], $r[4]];
                    $when = $at($offset, $c === 'Gaji' ? 9 : null);
                    if ($when->gt($now)) continue; // jangan membuat transaksi di masa depan
                    $type = $c === 'Gaji' ? 'income' : 'expense';
                    Transaction::create([
                        'household_id' => $hid, 'type' => $type, 'amount' => $amt, 'category_id' => $cat($c, $type),
                        'account_id' => $acc[$a], 'occurred_at' => $when->copy()->utc(), 'note' => $note, 'created_by' => $uid, 'paid_by' => $uid,
                    ]);
                }
                // Setoran bulanan ke Tabungan Nikah sehari setelah gajian
                $deposit = $at(1, 10);
                if ($deposit->lte($now)) {
                    SavingEntry::create(['goal_id' => $goal->id, 'amount' => 3_000_000, 'contributed_by' => $uid, 'occurred_at' => $deposit->copy()->utc(), 'note' => 'Setoran bulanan']);
                }
            }
            // Anggaran pribadi untuk periode berjalan
            foreach ([['Makan & Minum', 1_000_000], ['Belanja Harian', 1_200_000], ['Transportasi', 400_000], ['Hiburan', 400_000], ['Belanja', 500_000]] as [$n, $l]) {
                Budget::updateOrCreate(['household_id' => $hid, 'user_id' => $uid, 'category_id' => $cat($n, 'expense'), 'month' => $cur.'-01'], ['limit_amount' => $l]);
            }
        }

        $this->command->info("Data contoh dibuat untuk dompet #{$hid}: gaji Rp10.000.000 per orang, periode mulai tanggal {$cycle}, tabungan 'Tabungan Nikah'.");
    }
}
