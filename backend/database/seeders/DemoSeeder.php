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
use Illuminate\Database\Seeder;

/**
 * Data contoh: dua akun, saldo masing-masing, tabungan bersama.
 *   php artisan db:seed --class=DemoSeeder                 (email default fathur@gmail.com)
 *   DEMO_EMAIL=lain@x.id php artisan db:seed --class=DemoSeeder
 *   DEMO_FRESH=1 ...   menghapus transaksi/anggaran/tabungan dompet itu dulu, lalu mengisi ulang
 * Tanpa DEMO_FRESH, seeder menolak jalan bila dompet sudah punya transaksi.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $him = User::where('email', env('DEMO_EMAIL', 'fathur@gmail.com'))->firstOrFail();
        $hid = HouseholdMember::where('user_id', $him->id)->value('household_id');
        abort_if(! $hid, 1, 'User belum punya dompet.');
        $ids = HouseholdMember::where('household_id', $hid)->pluck('user_id');
        abort_if($ids->count() < 2, 1, 'Dompet belum berisi 2 orang.');
        $her = $ids->first(fn ($id) => $id !== $him->id);

        if (env('DEMO_FRESH')) {
            Transaction::withTrashed()->where('household_id', $hid)->forceDelete();
            Budget::where('household_id', $hid)->delete();
            SavingGoal::where('household_id', $hid)->delete(); // entries ikut terhapus (cascade)
            Account::where('household_id', $hid)->where(fn ($q) => $q->whereNull('owner_id')->orWhereIn('owner_id', $ids))->delete();
        } elseif (Transaction::where('household_id', $hid)->exists()) {
            $this->command->warn('Dompet sudah punya transaksi, seeder dilewati. Pakai DEMO_FRESH=1 untuk mengisi ulang.');
            return;
        }

        $cat = fn (string $n, string $k) => Category::where('household_id', $hid)->where('name', $n)->where('kind', $k)->value('id');
        $account = fn (int $uid, string $name, string $type) => Account::firstOrCreate(
            ['household_id' => $hid, 'owner_id' => $uid, 'name' => $name], ['type' => $type, 'initial_balance' => 0])->id;

        // [hari, kategori, nominal, akun('t' tunai | 'b' BCA), catatan]
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

        foreach ([$him->id => 'Gaji Fathur', $her => 'Gaji Wulan'] as $uid => $salaryNote) {
            $acc = ['t' => $account($uid, 'Tunai', 'cash'), 'b' => $account($uid, 'BCA', 'bank')];
            foreach ([now()->subMonthNoOverflow()->startOfMonth(), now()->startOfMonth()] as $m) {
                $at = fn (int $d) => $m->copy()->day(min($d, $m->daysInMonth))->setTime(rand(8, 20), rand(0, 59));
                $rows = array_merge([[1, 'Gaji', 10_000_000, 'b', $salaryNote]], $plans[$uid]);
                foreach ($rows as [$d, $c, $amt, $a, $note]) {
                    if ($m->copy()->day(min($d, $m->daysInMonth))->gt(now())) continue;
                    $type = $c === 'Gaji' ? 'income' : 'expense';
                    Transaction::create([
                        'household_id' => $hid, 'type' => $type, 'amount' => $amt, 'category_id' => $cat($c, $type),
                        'account_id' => $acc[$a], 'occurred_at' => $at($d), 'note' => $note, 'created_by' => $uid, 'paid_by' => $uid,
                    ]);
                }
            }
            // Anggaran pribadi bulan ini
            $month = now()->startOfMonth()->toDateString();
            foreach ([['Makan & Minum', 1_000_000], ['Belanja Harian', 1_000_000], ['Transportasi', 400_000], ['Hiburan', 400_000], ['Belanja', 500_000]] as [$n, $l]) {
                Budget::updateOrCreate(['household_id' => $hid, 'user_id' => $uid, 'category_id' => $cat($n, 'expense'), 'month' => $month], ['limit_amount' => $l]);
            }
        }

        // Tabungan: satu-satunya yang dipakai bersama
        $goal = fn (string $n, int $target, string $icon, string $color, string $date) => SavingGoal::create([
            'household_id' => $hid, 'name' => $n, 'target_amount' => $target, 'icon' => $icon, 'color' => $color,
            'target_date' => $date, 'created_by' => $him->id]);
        $entry = fn ($g, int $amt, int $by, int $daysAgo, string $note) => SavingEntry::create([
            'goal_id' => $g->id, 'amount' => $amt, 'contributed_by' => $by, 'occurred_at' => now()->subDays($daysAgo), 'note' => $note]);

        $g1 = $goal('Dana Darurat', 30_000_000, 'Shield', 'teal', now()->addMonths(9)->toDateString());
        $entry($g1, 2_000_000, $him->id, 40, 'Setoran awal'); $entry($g1, 2_000_000, $her, 40, 'Setoran awal');
        $entry($g1, 1_500_000, $him->id, 10, 'Setoran rutin'); $entry($g1, 1_500_000, $her, 10, 'Setoran rutin');
        $g2 = $goal('Liburan Bali', 12_000_000, 'Airplane', 'mustard', now()->addMonths(6)->toDateString());
        $entry($g2, 1_000_000, $her, 20, 'Sisa belanja'); $entry($g2, 1_000_000, $him->id, 8, 'Bonus kecil');

        $this->command->info("Data contoh dibuat untuk dompet #{$hid}: gaji masing-masing Rp10.000.000, saldo terpisah, tabungan bersama.");
    }
}
