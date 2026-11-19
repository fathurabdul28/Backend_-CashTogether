<?php

namespace App\Services\WhatsApp;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Period;
use App\Support\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Otak bot: pesan masuk (nomor + teks) -> teks balasan. Tidak tahu apa-apa soal HTTP. */
class BotHandler
{
    private const MAX_ITEMS = 10;

    public function __construct(private MessageParser $parser) {}

    public static function normalizePhone(string $raw): string
    {
        $d = preg_replace('/\D/', '', $raw);
        if (str_starts_with($d, '0')) return '62'.substr($d, 1);
        if (str_starts_with($d, '8')) return '62'.$d;
        return $d;
    }

    public function handle(string $sender, string $text): string
    {
        $phone = self::normalizePhone($sender);
        $text = trim($text);
        $cmd = mb_strtolower($text);

        if (preg_match('/^hubungkan\s+(\d{6})$/i', $text, $m)) return $this->link($phone, $m[1]);

        $user = User::where('phone', $phone)->first();
        if (! $user) {
            return "Nomor ini belum terhubung ke CashTogether.\nBuka app CashTogether → Pengaturan → Hubungkan WhatsApp, lalu kirim kode yang muncul ke sini (contoh: HUBUNGKAN 123456).";
        }
        $hid = $user->householdId();
        if (! $hid) return 'Kamu belum punya dompet bersama. Buka app CashTogether dulu untuk membuatnya atau bergabung.';

        if (in_array($cmd, ['bantuan', 'help', 'menu', '?'], true)) return $this->help();
        if (in_array($cmd, ['saldo', 'cek saldo'], true)) return 'Saldo kamu saat ini '.$this->idr(Wallet::balance($user->id, $hid)).'.';
        if (in_array($cmd, ['rekap', 'hari ini', 'laporan'], true)) return $this->recap($user, $hid);
        if (in_array($cmd, ['batal', 'undo', 'hapus'], true)) return $this->undo($user, $hid);

        $accounts = Account::where('household_id', $hid)->where('owner_id', $user->id)->where('archived', false)->get();
        [$account, $text] = $this->pickAccount($accounts, $text);
        if (! $account) return 'Kamu belum punya akun di CashTogether. Buka app dulu ya.';

        $items = $this->parser->parseMany($text);
        if (! $items) {
            return "Aku belum bisa membaca nominalnya.\nCoba tulis seperti ini:\n• pengeluaran 34 ribu untuk makan\n• beli bensin 50rb\n• gaji 10 juta\n• makan 25rb dan kopi 15rb (beberapa sekaligus)\nKetik BANTUAN untuk daftar perintah.";
        }
        if (count($items) > self::MAX_ITEMS) {
            return 'Maksimal '.self::MAX_ITEMS.' transaksi per pesan. Kirim dalam beberapa pesan ya.';
        }

        // Cari semua kategori dulu; kalau ada yang gagal, tidak ada yang tercatat
        $rows = [];
        foreach ($items as $p) {
            $category = Category::where('household_id', $hid)->where('kind', $p['type'])->where('name', $p['category'])->first()
                ?? Category::where('household_id', $hid)->where('kind', $p['type'])->where('name', 'Lainnya')->first();
            if (! $category) return 'Kategori tidak ditemukan. Coba lagi sebentar ya.';
            $rows[] = [$p, $category];
        }

        // Satu pesan = satu batch_id, supaya BATAL bisa membatalkan semua barang dari pesan itu sekaligus
        $batchId = (string) Str::ulid();
        DB::transaction(function () use ($rows, $hid, $account, $user, $batchId) {
            foreach ($rows as [$p, $category]) {
                Transaction::create([
                    'household_id' => $hid, 'type' => $p['type'], 'amount' => $p['amount'], 'category_id' => $category->id,
                    'account_id' => $account->id, 'occurred_at' => $p['date']->copy()->utc(), 'note' => $p['note'],
                    'source' => 'whatsapp', 'batch_id' => $batchId, 'created_by' => $user->id, 'paid_by' => $user->id,
                ]);
            }
        });

        $dateLabel = $this->dateLabel($rows[0][0]['date']);
        $balance = $this->idr(Wallet::balance($user->id, $hid));

        if (count($rows) === 1) {
            [$p, $category] = $rows[0];
            $label = $p['type'] === 'income' ? 'Pemasukan' : 'Pengeluaran';
            return "✅ {$label} {$this->idr($p['amount'])} untuk {$category->name} dicatat pada {$dateLabel}.\n"
                ."Akun: {$account->name} · Saldo kamu {$balance}.\n"
                .'Salah? Balas BATAL.';
        }

        $lines = [];
        foreach ($rows as $i => [$p, $category]) {
            $label = $p['type'] === 'income' ? 'Pemasukan' : 'Pengeluaran';
            $lines[] = ($i + 1).". {$label} {$this->idr($p['amount'])} · {$category->name}".($p['note'] ? " ({$p['note']})" : '');
        }
        return '✅ Dicatat '.count($rows)." transaksi pada {$dateLabel}:\n".implode("\n", $lines)."\n"
            ."Akun: {$account->name} · Saldo kamu {$balance}.\n"
            .'Salah? Balas BATAL untuk membatalkan semuanya.';
    }

    private function link(string $phone, string $code): string
    {
        $user = User::where('wa_link_code', $code)->where('wa_link_expires_at', '>', now())->first();
        if (! $user) return 'Kode tidak valid atau sudah kedaluwarsa. Buat kode baru di app CashTogether → Pengaturan → Hubungkan WhatsApp.';

        $other = User::where('phone', $phone)->where('id', '!=', $user->id)->first();
        if ($other) return 'Nomor ini sudah terhubung ke akun lain.';

        $user->forceFill(['phone' => $phone, 'wa_link_code' => null, 'wa_link_expires_at' => null])->save();
        return "Terhubung! Halo {$user->name}.\nSekarang kamu bisa mencatat lewat chat, contoh: pengeluaran 34 ribu untuk makan.\nKetik BANTUAN untuk daftar perintah.";
    }

    private function help(): string
    {
        return "Cara mencatat:\n• pengeluaran 34 ribu untuk makan\n• beli bensin 50rb kemarin\n• gaji 10 juta\n• makan siang 25k pakai BCA\n• makan 25rb dan kopi 15rb (beberapa sekaligus)\n\nPerintah:\n• SALDO — cek saldo\n• REKAP — ringkasan hari ini & bulan ini\n• BATAL — hapus catatan terakhir dari WhatsApp";
    }

    private function recap(User $user, int $hid): string
    {
        \App\Services\RecurringIncomes::apply($user->id, $hid);
        $tz = Period::tz();
        $today = Carbon::now($tz);
        $cycle = Period::clampDay($user->cycle_start_day);
        $q = fn (Carbon $from, Carbon $to) => Transaction::where('household_id', $hid)->where('created_by', $user->id)
            ->whereBetween('occurred_at', [$from->copy()->utc(), $to->copy()->utc()])->get(['type', 'amount']);
        $d = $q($today->copy()->startOfDay(), $today->copy()->endOfDay());
        $key = Period::currentKey($cycle);
        $m = $q(...Period::local($key, $cycle));
        $name = $cycle <= 1 ? 'Bulan ini' : 'Periode ini ('.Period::label($key, $cycle).')';
        return "Hari ini: masuk {$this->idr((int) $d->where('type', 'income')->sum('amount'))}, keluar {$this->idr((int) $d->where('type', 'expense')->sum('amount'))}.\n"
            ."{$name}: masuk {$this->idr((int) $m->where('type', 'income')->sum('amount'))}, keluar {$this->idr((int) $m->where('type', 'expense')->sum('amount'))}.\n"
            ."Saldo kamu {$this->idr(Wallet::balance($user->id, $hid))}.";
    }

    private function undo(User $user, int $hid): string
    {
        $last = Transaction::where('household_id', $hid)->where('created_by', $user->id)->where('source', 'whatsapp')
            ->where('created_at', '>=', now()->subDay())->orderByDesc('id')->first();
        if (! $last) return 'Tidak ada catatan dari WhatsApp (24 jam terakhir) yang bisa dibatalkan.';

        // semua barang dari pesan yang sama berbagi batch_id (catatan lama tanpa batch_id dibatalkan satu per satu)
        $batch = $last->batch_id
            ? Transaction::where('household_id', $hid)->where('created_by', $user->id)->where('batch_id', $last->batch_id)->get()
            : collect([$last]);
        $batch->each->delete();
        $balance = $this->idr(Wallet::balance($user->id, $hid));

        if ($batch->count() === 1) {
            $kind = $last->type === 'income' ? 'Pemasukan' : 'Pengeluaran';
            return "Dibatalkan: {$kind} {$this->idr($last->amount)}. Saldo kamu {$balance}.";
        }
        $lines = $batch->sortBy('id')->map(fn ($t) => '• '.($t->type === 'income' ? 'Pemasukan' : 'Pengeluaran').' '.$this->idr($t->amount))->implode("\n");
        return "Dibatalkan {$batch->count()} transaksi dari pesan terakhir:\n{$lines}\nSaldo kamu {$balance}.";
    }

    /** Cari nama akun milik user di pesan ("pakai bca", "via gopay"), lalu buang dari teks. */
    private function pickAccount($accounts, string $text): array
    {
        foreach ($accounts as $a) {
            $re = '/(?:\b(?:pakai|pake|via|dari|dengan|dgn|lewat|ke)\s+)?(?<![\p{L}\p{N}])'.preg_quote($a->name, '/').'(?![\p{L}\p{N}])/iu';
            if (preg_match($re, $text) && mb_strtolower($a->name) !== 'tunai') {
                return [$a, trim(preg_replace($re, ' ', $text))];
            }
        }
        $cash = $accounts->firstWhere('type', 'cash') ?? $accounts->first();
        return [$cash, $text];
    }

    private function dateLabel(Carbon $d): string
    {
        $tz = config('services.fonnte.timezone', 'Asia/Jakarta');
        $today = Carbon::now($tz)->startOfDay();
        $full = $d->copy()->locale('id')->translatedFormat('j F Y');
        $diff = (int) $today->diffInDays($d->copy()->startOfDay(), false);
        return match (true) { $diff === 0 => "{$full} (hari ini)", $diff === -1 => "{$full} (kemarin)", default => $full };
    }

    private function idr(int $n): string
    {
        return ($n < 0 ? '-' : '').'Rp'.number_format(abs($n), 0, ',', '.');
    }
}
